<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentActorEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\AdmissionSetting;
use App\Models\Applicant;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\Batch;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    $this->batch = Batch::create([
        'name' => 'EMBA Bkash Test '.uniqid(),
        'code' => 'EMBA-BK-'.uniqid(),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    AdmissionSetting::create([
        'batch_id' => $this->batch->id,
        'application_fee' => 2500,
        'enrollment_fee' => 500,
        'admission_fee' => 12000,
        'intake_started_at' => now()->subDay()->toDateString(),
        'intake_ended_at' => now()->addMonth()->toDateString(),
        'application_payment_ended_at' => now()->addMonth()->toDateString(),
    ]);

    $this->applicant = Applicant::factory()->create([
        'batch_id' => $this->batch->id,
        'email_verified_at' => now(),
    ]);

    ApplicantProfile::create([
        'applicant_id' => $this->applicant->id,
        'batch_id' => $this->batch->id,
        'full_name' => 'Pay Tester',
        'father_name' => 'Father',
        'mother_name' => 'Mother',
        'date_of_birth' => '1990-01-01',
    ]);

    $this->application = Application::create([
        'applicant_id' => $this->applicant->id,
        'batch_id' => $this->batch->id,
        'application_number' => $this->batch->code.'-T0001',
        'status' => ApplicationStatusEnum::AWAITING_PAYMENT,
        'payment_status' => PaymentStatusEnum::UNPAID,
        'applied_at' => now(),
    ]);

    $this->actingAs($this->applicant, 'applicant');
});

it('guests cannot initiate a bKash payment', function () {
    auth('applicant')->logout();

    $this->post(route('applicant.payment.bkash.initiate'))
        ->assertRedirect(route('applicant.login'));
});

it('redirects to bKash hosted page and creates a pending payment row on success', function () {
    Http::fake([
        '*/token/grant' => Http::response([
            'statusCode' => '0000',
            'id_token' => 'fake-id-token',
            'refresh_token' => 'fake-refresh-token',
            'expires_in' => 3600,
        ]),
        '*/checkout/create' => Http::response([
            'statusCode' => '0000',
            'paymentID' => 'PMTID-FAKE-1',
            'bkashURL' => 'https://sandbox.bka.sh/pay/PMTID-FAKE-1',
        ]),
    ]);

    $this->post(route('applicant.payment.bkash.initiate'))
        ->assertRedirect('https://sandbox.bka.sh/pay/PMTID-FAKE-1');

    expect(Payment::count())->toBe(1);

    $payment = Payment::first();
    expect($payment->status)->toBe(PaymentStatusEnum::PENDING)
        ->and($payment->gateway_payment_id)->toBe('PMTID-FAKE-1')
        ->and((float) $payment->amount)->toBe(2500.0)
        ->and($payment->actor_id)->toBe($this->application->id);
});

it('blocks initiation if the application has not been submitted', function () {
    $this->application->update(['applied_at' => null, 'status' => ApplicationStatusEnum::PENDING]);

    $this->post(route('applicant.payment.bkash.initiate'))->assertRedirect();
    expect(Payment::count())->toBe(0);
});

it('blocks initiation if the application is already paid', function () {
    $this->application->update(['payment_status' => PaymentStatusEnum::PAID]);

    $this->post(route('applicant.payment.bkash.initiate'))->assertRedirect();
    expect(Payment::count())->toBe(0);
});

it('blocks initiation if the payment deadline has passed', function () {
    AdmissionSetting::where('batch_id', $this->batch->id)
        ->update(['application_payment_ended_at' => now()->subDay()->toDateString()]);

    $this->post(route('applicant.payment.bkash.initiate'))->assertRedirect();
    expect(Payment::count())->toBe(0);
});

it('marks application as paid on a successful callback', function () {
    $payment = Payment::create([
        'payment_number' => 'PMT-'.uniqid(),
        'batch_id' => $this->batch->id,
        'applicant_id' => $this->applicant->id,
        'actor_table' => PaymentActorEnum::APPLICATION,
        'actor_id' => $this->application->id,
        'payment_method' => PaymentMethodEnum::BKASH,
        'amount' => 2500,
        'status' => PaymentStatusEnum::PENDING,
        'gateway_payment_id' => 'PMTID-OK-1',
    ]);

    Http::fake([
        '*/token/grant' => Http::response([
            'statusCode' => '0000',
            'id_token' => 'fake-id-token',
            'refresh_token' => 'fake-refresh-token',
            'expires_in' => 3600,
        ]),
        '*/checkout/execute' => Http::response([
            'statusCode' => '0000',
            'paymentID' => 'PMTID-OK-1',
            'trxID' => 'TRX-OK-1',
            'amount' => '2500.00',
            'transactionStatus' => 'Completed',
        ]),
    ]);

    $this->get(route('applicant.payment.bkash.callback', ['paymentID' => 'PMTID-OK-1', 'status' => 'success']))
        ->assertRedirect(route('applicant.application'));

    $this->application->refresh();
    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatusEnum::COMPLETED)
        ->and($payment->gateway_trx_id)->toBe('TRX-OK-1')
        ->and($this->application->payment_status)->toBe(PaymentStatusEnum::PAID)
        ->and($this->application->status)->toBe(ApplicationStatusEnum::COMPLETED)
        ->and($this->application->trx_id)->toBe('TRX-OK-1')
        ->and($this->application->payment_method)->toBe(PaymentMethodEnum::BKASH);
});

it('marks payment as failed when the customer cancels at bKash', function () {
    $payment = Payment::create([
        'payment_number' => 'PMT-'.uniqid(),
        'batch_id' => $this->batch->id,
        'applicant_id' => $this->applicant->id,
        'actor_table' => PaymentActorEnum::APPLICATION,
        'actor_id' => $this->application->id,
        'payment_method' => PaymentMethodEnum::BKASH,
        'amount' => 2500,
        'status' => PaymentStatusEnum::PENDING,
        'gateway_payment_id' => 'PMTID-CXL-1',
    ]);

    $this->get(route('applicant.payment.bkash.callback', ['paymentID' => 'PMTID-CXL-1', 'status' => 'cancel']))
        ->assertRedirect(route('applicant.application'));

    expect($payment->fresh()->status)->toBe(PaymentStatusEnum::FAILED);
    expect($this->application->fresh()->payment_status)->toBe(PaymentStatusEnum::UNPAID);
});

it('keeps payment pending if bKash reports a non-Completed status on execute', function () {
    $payment = Payment::create([
        'payment_number' => 'PMT-'.uniqid(),
        'batch_id' => $this->batch->id,
        'applicant_id' => $this->applicant->id,
        'actor_table' => PaymentActorEnum::APPLICATION,
        'actor_id' => $this->application->id,
        'payment_method' => PaymentMethodEnum::BKASH,
        'amount' => 2500,
        'status' => PaymentStatusEnum::PENDING,
        'gateway_payment_id' => 'PMTID-PEND-1',
    ]);

    Http::fake([
        '*/token/grant' => Http::response([
            'statusCode' => '0000',
            'id_token' => 'fake-id-token',
            'refresh_token' => 'fake-refresh-token',
            'expires_in' => 3600,
        ]),
        '*/checkout/execute' => Http::response([
            'statusCode' => '0000',
            'paymentID' => 'PMTID-PEND-1',
            'transactionStatus' => 'Pending',
        ]),
    ]);

    $this->get(route('applicant.payment.bkash.callback', ['paymentID' => 'PMTID-PEND-1', 'status' => 'success']))
        ->assertRedirect(route('applicant.application'));

    expect($payment->fresh()->status)->toBe(PaymentStatusEnum::FAILED);
    expect($this->application->fresh()->payment_status)->toBe(PaymentStatusEnum::UNPAID);
});
