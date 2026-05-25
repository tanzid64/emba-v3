<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Applicant;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\Batch;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->batch = Batch::create([
        'name' => 'EMBA Verify Test '.uniqid(),
        'code' => 'EMBA-VR-'.uniqid(),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    $this->applicant = Applicant::factory()->create([
        'batch_id' => $this->batch->id,
    ]);

    ApplicantProfile::create([
        'applicant_id' => $this->applicant->id,
        'batch_id' => $this->batch->id,
        'full_name' => 'Verified Applicant',
        'father_name' => 'Father',
        'mother_name' => 'Mother',
        'date_of_birth' => '1990-01-01',
    ]);

    $this->application = Application::create([
        'applicant_id' => $this->applicant->id,
        'batch_id' => $this->batch->id,
        'application_number' => $this->batch->code.'-VR-001',
        'status' => ApplicationStatusEnum::COMPLETED,
        'payment_status' => PaymentStatusEnum::PAID,
        'applied_at' => now(),
    ]);
});

it('renders the signed verification page successfully', function () {
    $url = URL::signedRoute('verify.application', ['appNo' => $this->application->application_number]);

    $this->get($url)
        ->assertOk()
        ->assertSee('Authentic application')
        ->assertSee('Verified Applicant')
        ->assertSee($this->application->application_number)
        ->assertSee($this->batch->name);
});

it('rejects an unsigned URL with 403', function () {
    $this->get('/verify/application/'.$this->application->application_number)
        ->assertForbidden();
});

it('rejects a URL whose signature is tampered with', function () {
    $url = URL::signedRoute('verify.application', ['appNo' => $this->application->application_number]);

    $this->get($url.'tampered')->assertForbidden();
});

it('returns 404 for an unknown application even with a valid signature', function () {
    $url = URL::signedRoute('verify.application', ['appNo' => 'NOPE-9999']);

    $this->get($url)->assertNotFound();
});
