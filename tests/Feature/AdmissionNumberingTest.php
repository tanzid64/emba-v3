<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\AdmissionSetting;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Batch;
use App\Services\AdmissionNumberingService;

function makeBatchWithSettings(array $settingOverrides = []): Batch
{
    $batch = Batch::create([
        'name' => 'EMBA AN '.uniqid(),
        'code' => 'EMBA-AN-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    AdmissionSetting::create(array_merge([
        'batch_id' => $batch->id,
        'application_number_start_from' => 1000,
        'roll_number_start_from' => 1000,
    ], $settingOverrides));

    return $batch->refresh();
}

function makeApplication(Batch $batch, array $overrides = []): Application
{
    $applicant = Applicant::factory()->create(['batch_id' => $batch->id]);

    return Application::create(array_merge([
        'applicant_id' => $applicant->id,
        'batch_id' => $batch->id,
        'status' => ApplicationStatusEnum::PENDING,
        'payment_status' => PaymentStatusEnum::UNPAID,
    ], $overrides));
}

it('returns start_from as the first application number for a batch', function () {
    $batch = makeBatchWithSettings(['application_number_start_from' => 1500]);

    $service = app(AdmissionNumberingService::class);

    expect($service->nextApplicationNumber($batch))->toBe($batch->code.'-1500');
});

it('increments the next application number by 1', function () {
    $batch = makeBatchWithSettings();
    $service = app(AdmissionNumberingService::class);

    $first = $service->nextApplicationNumber($batch);
    makeApplication($batch, ['application_number' => $first]);

    expect($service->nextApplicationNumber($batch))->toBe($batch->code.'-1001');
});

it('isolates the sequence per batch', function () {
    $batchA = makeBatchWithSettings(['application_number_start_from' => 100]);
    $batchB = makeBatchWithSettings(['application_number_start_from' => 500]);

    $service = app(AdmissionNumberingService::class);

    expect($service->nextApplicationNumber($batchA))->toBe($batchA->code.'-100');
    expect($service->nextApplicationNumber($batchB))->toBe($batchB->code.'-500');
});

it('raises the counter when start_from is bumped above current max', function () {
    $batch = makeBatchWithSettings();
    makeApplication($batch, ['application_number' => $batch->code.'-1000']);

    $batch->admissionSetting->update(['application_number_start_from' => 2000]);

    $service = app(AdmissionNumberingService::class);

    expect($service->nextApplicationNumber($batch))->toBe($batch->code.'-2000');
});

it('overrides a start_from that is below current max with max+1', function () {
    $batch = makeBatchWithSettings(['application_number_start_from' => 1000]);
    makeApplication($batch, ['application_number' => $batch->code.'-1050']);

    $batch->admissionSetting->update(['application_number_start_from' => 1010]);

    $service = app(AdmissionNumberingService::class);

    expect($service->nextApplicationNumber($batch))->toBe($batch->code.'-1051');
});

it('starts roll numbers from roll_number_start_from', function () {
    $batch = makeBatchWithSettings(['roll_number_start_from' => 2500]);
    $application = makeApplication($batch);

    $service = app(AdmissionNumberingService::class);

    expect($service->nextRollNumber($application))->toBe('2500');
});

it('is idempotent when the application already has a roll number', function () {
    $batch = makeBatchWithSettings();
    $application = makeApplication($batch, ['roll_number' => '9999']);

    $service = app(AdmissionNumberingService::class);

    expect($service->nextRollNumber($application))->toBe('9999');

    // Counter should not have advanced — a fresh application gets start_from, not 10000.
    $fresh = makeApplication($batch);
    expect($service->nextRollNumber($fresh))->toBe('1000');
});

it('produces distinct numbers across back-to-back calls in the same batch', function () {
    // True parallel concurrency cannot be exercised in SQLite (lockForUpdate is a no-op).
    // This test asserts the spec's intent: two sequential calls, with the first result
    // persisted before the second, yield strictly increasing numbers. In production the
    // lockForUpdate on admission_settings serializes concurrent callers the same way.
    $batch = makeBatchWithSettings();
    $service = app(AdmissionNumberingService::class);

    $first = $service->nextApplicationNumber($batch);
    makeApplication($batch, ['application_number' => $first]);

    $second = $service->nextApplicationNumber($batch);

    expect($first)->not->toBe($second);
    expect($second)->toBe($batch->code.'-1001');
});

it('increments the next roll number by 1 after assignment', function () {
    $batch = makeBatchWithSettings();
    $service = app(AdmissionNumberingService::class);

    $appOne = makeApplication($batch, ['application_number' => $batch->code.'-1000']);
    $appOne->roll_number = $service->nextRollNumber($appOne);
    $appOne->save();

    $appTwo = makeApplication($batch, ['application_number' => $batch->code.'-1001']);

    expect($service->nextRollNumber($appTwo))->toBe('1001');
});
