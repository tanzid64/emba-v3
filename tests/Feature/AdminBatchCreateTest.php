<?php

use App\Enum\BatchStatusEnum;
use App\Models\AdmissionSetting;
use App\Models\Batch;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('persists application_number_start_from and roll_number_start_from when creating a batch', function () {
    Livewire\Livewire::test('pages::admin.batches.create')
        ->set('batch.name', 'EMBA Test '.uniqid())
        ->set('batch.code', 'EMBA-T-'.strtoupper(substr(uniqid(), -4)))
        ->set('batch.admission_year', 2026)
        ->set('batch.status', BatchStatusEnum::DRAFT->value)
        ->set('settings.application_fee', 2500)
        ->set('settings.enrollment_fee', 500)
        ->set('settings.admission_fee', 12000)
        ->set('settings.application_number_start_from', 1700)
        ->set('settings.roll_number_start_from', 1800)
        ->call('save');

    $batch = Batch::latest('id')->first();
    expect($batch)->not->toBeNull();

    $setting = AdmissionSetting::where('batch_id', $batch->id)->first();
    expect($setting->application_number_start_from)->toBe(1700);
    expect($setting->roll_number_start_from)->toBe(1800);
});
