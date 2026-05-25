<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentActorEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Applicant;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\Batch;
use App\Models\Payment;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->batch = Batch::create([
        'name' => 'EMBA Verify Pay '.uniqid(),
        'code' => 'EMBA-VP-'.uniqid(),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    $this->applicant = Applicant::factory()->create(['batch_id' => $this->batch->id]);

    ApplicantProfile::create([
        'applicant_id' => $this->applicant->id,
        'batch_id' => $this->batch->id,
        'full_name' => 'Receipt Tester',
        'father_name' => 'Father',
        'mother_name' => 'Mother',
        'date_of_birth' => '1990-01-01',
    ]);

    $this->application = Application::create([
        'applicant_id' => $this->applicant->id,
        'batch_id' => $this->batch->id,
        'application_number' => $this->batch->code.'-VP-001',
        'status' => ApplicationStatusEnum::COMPLETED,
        'payment_status' => PaymentStatusEnum::PAID,
        'applied_at' => now(),
    ]);

    $this->payment = Payment::create([
        'payment_number' => 'PMT-VR-'.uniqid(),
        'batch_id' => $this->batch->id,
        'applicant_id' => $this->applicant->id,
        'actor_table' => PaymentActorEnum::APPLICATION,
        'actor_id' => $this->application->id,
        'payment_method' => PaymentMethodEnum::BKASH,
        'amount' => 2500,
        'status' => PaymentStatusEnum::COMPLETED,
        'gateway_trx_id' => 'TRX-VR-1',
        'gateway_payment_id' => 'PAYID-VR-1',
        'paid_at' => now(),
    ]);
});

it('renders the signed payment verification page successfully', function () {
    $url = URL::signedRoute('verify.payment', ['paymentNo' => $this->payment->payment_number]);

    $this->get($url)
        ->assertOk()
        ->assertSee('Authentic payment')
        ->assertSee('Receipt Tester')
        ->assertSee($this->payment->payment_number)
        ->assertSee('TRX-VR-1')
        ->assertSee($this->batch->name);
});

it('rejects an unsigned payment verification URL with 403', function () {
    $this->get('/verify/payment/'.$this->payment->payment_number)
        ->assertForbidden();
});

it('rejects a payment URL whose signature is tampered with', function () {
    $url = URL::signedRoute('verify.payment', ['paymentNo' => $this->payment->payment_number]);

    $this->get($url.'tampered')->assertForbidden();
});

it('returns 404 for an unknown payment even with a valid signature', function () {
    $url = URL::signedRoute('verify.payment', ['paymentNo' => 'PMT-NOPE-9999']);

    $this->get($url)->assertNotFound();
});

it('shows a warning state when the payment is not completed', function () {
    $this->payment->update(['status' => PaymentStatusEnum::FAILED]);

    $url = URL::signedRoute('verify.payment', ['paymentNo' => $this->payment->payment_number]);

    $this->get($url)
        ->assertOk()
        ->assertSee('Payment not completed')
        ->assertDontSee('Authentic payment');
});
