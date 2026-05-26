<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\AdmissionSetting;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Batch;
use App\Models\User;
use App\Support\CurrentBatch;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->batch = Batch::create([
        'name' => 'EMBA Confirmed '.uniqid(),
        'code' => 'EMBA-CF-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    AdmissionSetting::create([
        'batch_id' => $this->batch->id,
        'application_number_start_from' => 1000,
        'roll_number_start_from' => 1000,
    ]);

    CurrentBatch::set($this->batch->id);
});

function makeApp(Batch $batch, PaymentStatusEnum $paymentStatus, ?string $rollNumber = null): Application
{
    $applicant = Applicant::factory()->create(['batch_id' => $batch->id]);

    return Application::create([
        'applicant_id' => $applicant->id,
        'batch_id' => $batch->id,
        'application_number' => $batch->code.'-'.fake()->unique()->numberBetween(1000, 9999),
        'roll_number' => $rollNumber,
        'status' => $paymentStatus === PaymentStatusEnum::PAID
            ? ApplicationStatusEnum::COMPLETED
            : ApplicationStatusEnum::AWAITING_PAYMENT,
        'payment_status' => $paymentStatus,
        'applied_at' => now(),
    ]);
}

it('lists only paid or completed applications', function () {
    $paid = makeApp($this->batch, PaymentStatusEnum::PAID, '1000');
    $completed = makeApp($this->batch, PaymentStatusEnum::COMPLETED, '1001');
    $unpaid = makeApp($this->batch, PaymentStatusEnum::UNPAID);

    Livewire::test('pages::admin.confirmed-applicants')
        ->assertOk()
        ->assertSee($paid->application_number)
        ->assertSee($completed->application_number)
        ->assertDontSee($unpaid->application_number);
});

it('searches by roll number and trx id', function () {
    makeApp($this->batch, PaymentStatusEnum::PAID, '1000');
    $target = makeApp($this->batch, PaymentStatusEnum::PAID, '5500');
    $target->update(['trx_id' => 'TRX-CONFIRMED-TEST']);

    Livewire::test('pages::admin.confirmed-applicants')
        ->set('search', '5500')
        ->assertSee($target->application_number)
        ->set('search', 'CONFIRMED-TEST')
        ->assertSee($target->application_number);
});
