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
        'name' => 'EMBA QS '.uniqid(),
        'code' => 'EMBA-QS-'.strtoupper(substr(uniqid(), -4)),
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

it('persists a new application_number_start_from when above current max', function () {
    Livewire::test('pages::admin.quick-settings')
        ->call('startEdit', 'application_number_start_from')
        ->set('fieldValue', 2500)
        ->call('saveField');

    expect($this->batch->fresh()->admissionSetting->application_number_start_from)->toBe(2500);
});

it('rejects an application_number_start_from below the current max', function () {
    $applicant = Applicant::factory()->create(['batch_id' => $this->batch->id]);
    Application::create([
        'applicant_id' => $applicant->id,
        'batch_id' => $this->batch->id,
        'application_number' => $this->batch->code.'-1200',
        'status' => ApplicationStatusEnum::AWAITING_PAYMENT,
        'payment_status' => PaymentStatusEnum::UNPAID,
        'applied_at' => now(),
    ]);

    Livewire::test('pages::admin.quick-settings')
        ->call('startEdit', 'application_number_start_from')
        ->set('fieldValue', 1100)
        ->call('saveField')
        ->assertHasErrors(['fieldValue']);

    expect($this->batch->fresh()->admissionSetting->application_number_start_from)->toBe(1000);
});

it('rejects a roll_number_start_from below the current max', function () {
    $applicant = Applicant::factory()->create(['batch_id' => $this->batch->id]);
    Application::create([
        'applicant_id' => $applicant->id,
        'batch_id' => $this->batch->id,
        'application_number' => $this->batch->code.'-1000',
        'roll_number' => '1500',
        'status' => ApplicationStatusEnum::COMPLETED,
        'payment_status' => PaymentStatusEnum::PAID,
        'applied_at' => now(),
    ]);

    Livewire::test('pages::admin.quick-settings')
        ->call('startEdit', 'roll_number_start_from')
        ->set('fieldValue', 1400)
        ->call('saveField')
        ->assertHasErrors(['fieldValue']);

    expect($this->batch->fresh()->admissionSetting->roll_number_start_from)->toBe(1000);
});
