<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Applicant;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\Batch;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->batch = Batch::create([
        'name' => 'EMBA Show Test '.uniqid(),
        'code' => 'EMBA-S-'.uniqid(),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    $applicant = Applicant::factory()->create([
        'batch_id' => $this->batch->id,
        'email' => 'show@example.com',
        'phone_number' => '01711112233',
    ]);

    ApplicantProfile::create([
        'applicant_id' => $applicant->id,
        'batch_id' => $this->batch->id,
        'full_name' => 'Mukta Roy',
        'father_name' => 'Madan Lal Roy',
        'mother_name' => 'Maloti Roy Kakoli',
        'date_of_birth' => '1990-01-01',
    ]);

    $this->application = Application::create([
        'applicant_id' => $applicant->id,
        'batch_id' => $this->batch->id,
        'application_number' => 'EMBA-25800320',
        'status' => ApplicationStatusEnum::AWAITING_PAYMENT,
        'payment_status' => PaymentStatusEnum::PAID,
        'payment_method' => PaymentMethodEnum::BKASH,
        'amount' => 2500,
        'trx_id' => 'CK54VQ9JM2',
        'payment_id' => 'PAYID12345',
        'paid_at' => now(),
        'applied_at' => now(),
    ]);
});

it('guests cannot view the applicant detail page', function () {
    auth()->logout();

    $this->get(route('admin.applicants.show', $this->application))
        ->assertRedirect(route('login'));
});

it('renders the application, profile, contact, and payment details', function () {
    $this->get(route('admin.applicants.show', $this->application))
        ->assertOk()
        ->assertSee('Mukta Roy')
        ->assertSee('EMBA-25800320')
        ->assertSee('Madan Lal Roy')
        ->assertSee('Maloti Roy Kakoli')
        ->assertSee('show@example.com')
        ->assertSee('01711112233')
        ->assertSee('CK54VQ9JM2')
        ->assertSee('PAYID12345')
        ->assertSee('Bkash')
        ->assertSee('Paid')
        ->assertSee('Application & Payment')
        ->assertSee('Applicant Profile')
        ->assertSee('Addresses')
        ->assertSee('Education History')
        ->assertSee('Experience History');
});

it('returns 404 for an unknown application', function () {
    $this->get('/admin/applicants/999999')->assertNotFound();
});
