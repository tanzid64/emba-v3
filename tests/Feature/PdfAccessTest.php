<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Batch;
use App\Models\User;

beforeEach(function () {
    $this->batch = Batch::create([
        'name' => 'EMBA PDF Test '.uniqid(),
        'code' => 'EMBA-PDF-'.uniqid(),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    $this->owner = Applicant::factory()->create(['batch_id' => $this->batch->id]);
    $this->stranger = Applicant::factory()->create(['batch_id' => $this->batch->id]);

    $this->application = Application::create([
        'applicant_id' => $this->owner->id,
        'batch_id' => $this->batch->id,
        'application_number' => $this->batch->code.'-PDF-001',
        'status' => ApplicationStatusEnum::AWAITING_PAYMENT,
        'payment_status' => PaymentStatusEnum::UNPAID,
        'applied_at' => now(),
    ]);
});

it('guests cannot access the application form PDF', function () {
    // Path is neither under /applicant nor under /admin, so the global
    // redirectGuestsTo rule sends them to the admin login by default.
    $this->get(route('pdf.application-form', $this->application->application_number))
        ->assertRedirect(route('login'));
});

it('admin users can access any applicant pdf', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin, 'web')
        ->get(route('pdf.application-form', $this->application->application_number))
        ->assertOk();
});

it('an applicant can access their own pdf', function () {
    $this->actingAs($this->owner, 'applicant')
        ->get(route('pdf.application-form', $this->application->application_number))
        ->assertOk();
});

it('an applicant cannot access another applicant pdf', function () {
    $this->actingAs($this->stranger, 'applicant')
        ->get(route('pdf.application-form', $this->application->application_number))
        ->assertForbidden();
});

it('returns 404 for an unknown application', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin, 'web')
        ->get('/pdf/application-form/9999999')
        ->assertNotFound();
});
