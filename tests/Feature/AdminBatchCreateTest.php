<?php

use App\Enum\BatchStatusEnum;
use App\Models\AdmissionSetting;
use App\Models\Batch;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('persists application_number_start_from and roll_number_start_from when creating a batch', function () {
    Livewire::test('pages::admin.batches.create')
        ->set('batch.name', 'EMBA Test '.uniqid())
        ->set('batch.code', 'EMBA-T-'.strtoupper(substr(uniqid(), -4)))
        ->set('batch.admission_year', 2026)
        ->set('batch.status', BatchStatusEnum::DRAFT->value)
        ->set('settings.application_fee', 2500)
        ->set('settings.enrollment_fee', 500)
        ->set('settings.admission_fee', 12000)
        ->set('settings.application_number_start_from', 1700)
        ->set('settings.roll_number_start_from', 1800)
        ->call('save')
        ->assertRedirect(route('admin.batches.index'));

    $batch = Batch::latest('id')->first();
    expect($batch)->not->toBeNull();

    $setting = AdmissionSetting::where('batch_id', $batch->id)->first();
    expect($setting->application_number_start_from)->toBe(1700);
    expect($setting->roll_number_start_from)->toBe(1800);
});

it('rejects a zero or negative application_number_start_from', function () {
    Livewire::test('pages::admin.batches.create')
        ->set('batch.name', 'EMBA NV '.uniqid())
        ->set('batch.code', 'EMBA-NV-'.strtoupper(substr(uniqid(), -4)))
        ->set('batch.admission_year', 2026)
        ->set('batch.status', BatchStatusEnum::DRAFT->value)
        ->set('settings.application_fee', 2500)
        ->set('settings.enrollment_fee', 500)
        ->set('settings.admission_fee', 12000)
        ->set('settings.application_number_start_from', 0)
        ->set('settings.roll_number_start_from', 1000)
        ->call('save')
        ->assertHasErrors(['settings.application_number_start_from']);

    expect(Batch::count())->toBe(0);
});

it('rejects a non-integer roll_number_start_from', function () {
    Livewire::test('pages::admin.batches.create')
        ->set('batch.name', 'EMBA NV '.uniqid())
        ->set('batch.code', 'EMBA-NV-'.strtoupper(substr(uniqid(), -4)))
        ->set('batch.admission_year', 2026)
        ->set('batch.status', BatchStatusEnum::DRAFT->value)
        ->set('settings.application_fee', 2500)
        ->set('settings.enrollment_fee', 500)
        ->set('settings.admission_fee', 12000)
        ->set('settings.application_number_start_from', 1000)
        ->set('settings.roll_number_start_from', 'abc')
        ->call('save')
        ->assertHasErrors(['settings.roll_number_start_from']);

    expect(Batch::count())->toBe(0);
});
