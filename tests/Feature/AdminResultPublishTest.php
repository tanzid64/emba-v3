<?php

use App\Models\AdmissionSetting;
use App\Models\Batch;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('publishes the result for the batch', function () {
    $batch = Batch::factory()->create();
    $setting = AdmissionSetting::create(['batch_id' => $batch->id]);

    expect($setting->is_result_published)->toBeFalse();

    Livewire::test('pages::admin.exam-results')
        ->set('admissionSetting', $setting)
        ->call('togglePublishResult');

    expect($setting->fresh()->result_published_at)->not->toBeNull();
});

it('unpublishes a published result for the batch', function () {
    $batch = Batch::factory()->create();
    $setting = AdmissionSetting::create([
        'batch_id' => $batch->id,
        'result_published_at' => now(),
    ]);

    expect($setting->is_result_published)->toBeTrue();

    Livewire::test('pages::admin.exam-results')
        ->set('admissionSetting', $setting)
        ->call('togglePublishResult');

    expect($setting->fresh()->result_published_at)->toBeNull();
});
