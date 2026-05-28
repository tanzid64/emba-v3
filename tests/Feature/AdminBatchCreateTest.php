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

it('persists pass_mark and viva_mcq_threshold when creating a batch', function () {
    Livewire::test('pages::admin.batches.create')
        ->set('batch.name', 'EMBA Mark '.uniqid())
        ->set('batch.code', 'EMBA-M-'.strtoupper(substr(uniqid(), -4)))
        ->set('batch.admission_year', 2026)
        ->set('batch.status', BatchStatusEnum::DRAFT->value)
        ->set('settings.application_fee', 2500)
        ->set('settings.enrollment_fee', 500)
        ->set('settings.admission_fee', 12000)
        ->set('settings.pass_mark', 45)
        ->set('settings.viva_mcq_threshold', 30)
        ->set('settings.application_number_start_from', 1000)
        ->set('settings.roll_number_start_from', 1000)
        ->call('save')
        ->assertRedirect(route('admin.batches.index'));

    $setting = AdmissionSetting::where('batch_id', Batch::latest('id')->first()->id)->first();
    expect((float) $setting->pass_mark)->toBe(45.0);
    expect((float) $setting->viva_mcq_threshold)->toBe(30.0);
});

it('defaults pass_mark to 40 and viva_mcq_threshold to 25 when left untouched', function () {
    Livewire::test('pages::admin.batches.create')
        ->set('batch.name', 'EMBA Def '.uniqid())
        ->set('batch.code', 'EMBA-D-'.strtoupper(substr(uniqid(), -4)))
        ->set('batch.admission_year', 2026)
        ->set('batch.status', BatchStatusEnum::DRAFT->value)
        ->call('save')
        ->assertRedirect(route('admin.batches.index'));

    $setting = AdmissionSetting::where('batch_id', Batch::latest('id')->first()->id)->first();
    expect((float) $setting->pass_mark)->toBe(40.0);
    expect((float) $setting->viva_mcq_threshold)->toBe(25.0);
});

it('rejects a pass_mark above the maximum total marks', function () {
    Livewire::test('pages::admin.batches.create')
        ->set('batch.name', 'EMBA PM '.uniqid())
        ->set('batch.code', 'EMBA-PM-'.strtoupper(substr(uniqid(), -4)))
        ->set('batch.admission_year', 2026)
        ->set('batch.status', BatchStatusEnum::DRAFT->value)
        ->set('settings.pass_mark', config('result.max_marks') + 1)
        ->call('save')
        ->assertHasErrors(['settings.pass_mark']);

    expect(Batch::count())->toBe(0);
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
