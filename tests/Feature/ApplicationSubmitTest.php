<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Models\AdmissionSetting;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Batch;

beforeEach(function () {
    $this->batch = Batch::create([
        'name' => 'EMBA Submit '.uniqid(),
        'code' => 'EMBA-SB-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    AdmissionSetting::create([
        'batch_id' => $this->batch->id,
        'application_number_start_from' => 1000,
        'roll_number_start_from' => 1000,
    ]);

    $this->applicant = Applicant::factory()->create(['batch_id' => $this->batch->id]);
});

it('creates a draft without an application_number', function () {
    $application = Application::draftFor($this->applicant);

    expect($application->application_number)->toBeNull();
    expect($application->status)->toBe(ApplicationStatusEnum::PENDING);
});

it('assigns a sequential application_number on submit', function () {
    $application = Application::draftFor($this->applicant);

    $application->submit();

    expect($application->fresh()->application_number)->toBe($this->batch->code.'-1000');
    expect($application->fresh()->status)->toBe(ApplicationStatusEnum::AWAITING_PAYMENT);
    expect($application->fresh()->is_applied)->toBeTrue();
});

it('does not re-assign application_number on a repeat submit', function () {
    $application = Application::draftFor($this->applicant);
    $application->submit();
    $first = $application->fresh()->application_number;

    Application::find($application->id)->submit();

    expect($application->fresh()->application_number)->toBe($first);
});
