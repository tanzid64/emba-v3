<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Enums\DegreeType;
use App\Enums\PaymentStatusEnum;
use App\Enums\ResultStatusEnum;
use App\Models\AdmissionResult;
use App\Models\AdmissionSetting;
use App\Models\Applicant;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\Batch;
use App\Models\EducationHistory;
use App\Services\ResultGenerationService;

function makeApplicantWithProfile(array $profileOverrides = [], array $degrees = []): array
{
    $batch = Batch::create([
        'name' => 'EMBA Test '.uniqid(),
        'code' => 'EMBA-T-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    $applicant = Applicant::factory()->create(['batch_id' => $batch->id]);

    ApplicantProfile::create(array_merge([
        'applicant_id' => $applicant->id,
        'batch_id' => $batch->id,
        'full_name' => 'TEST CANDIDATE',
        'father_name' => 'TEST FATHER',
        'mother_name' => 'TEST MOTHER',
        'date_of_birth' => '1990-01-01',
        'tot_year_of_schooling' => 16,
        'tot_year_of_exp' => 5,
    ], $profileOverrides));

    foreach ($degrees as $degree) {
        EducationHistory::create(array_merge([
            'applicant_id' => $applicant->id,
            'type' => DegreeType::UNDERGRADUATE->value,
            'name' => 'Honours',
            'major' => 'BBA',
            'institute' => 'Test Uni',
            'result' => '3.5',
            'scale' => '4.00',
            'passing_year' => 2020,
            'duration' => 4,
        ], $degree));
    }

    $application = Application::create([
        'applicant_id' => $applicant->id,
        'batch_id' => $batch->id,
        'application_number' => $batch->code.'-1000',
        'roll_number' => '1000',
        'status' => ApplicationStatusEnum::COMPLETED,
        'payment_status' => PaymentStatusEnum::PAID,
        'applied_at' => now(),
        'paid_at' => now(),
    ]);

    return compact('batch', 'applicant', 'application');
}

it('awards schooling marks per the bachelor matrix', function (int $years, int $expected) {
    ['application' => $application] = makeApplicantWithProfile(
        ['tot_year_of_schooling' => $years, 'tot_year_of_exp' => 0],
        [['type' => DegreeType::UNDERGRADUATE->value, 'major' => 'BBA']],
    );

    $result = app(ResultGenerationService::class)->generateForApplication($application);

    expect((int) $result->schooling_marks)->toBe($expected);
})->with([
    'bachelor 14yr → 2' => [14, 2],
    'bachelor 15yr → 3' => [15, 3],
    'bachelor 16yr → 4' => [16, 4],
]);

it('bumps 16yr Bachelor to 5 for engineering majors', function () {
    ['application' => $application] = makeApplicantWithProfile(
        ['tot_year_of_schooling' => 16, 'tot_year_of_exp' => 0],
        [['type' => DegreeType::UNDERGRADUATE->value, 'major' => 'Computer Engineering']],
    );

    $result = app(ResultGenerationService::class)->generateForApplication($application);

    expect((int) $result->schooling_marks)->toBe(5);
});

it('awards 4 for 16yr Master and 5 for 17yr Master', function () {
    ['application' => $a16] = makeApplicantWithProfile(
        ['tot_year_of_schooling' => 16, 'tot_year_of_exp' => 0],
        [
            ['type' => DegreeType::UNDERGRADUATE->value, 'major' => 'BBA'],
            ['type' => DegreeType::GRADUATE->value, 'major' => 'MBA'],
        ],
    );

    expect((int) app(ResultGenerationService::class)->generateForApplication($a16)->schooling_marks)
        ->toBe(4);

    ['application' => $a17] = makeApplicantWithProfile(
        ['tot_year_of_schooling' => 17, 'tot_year_of_exp' => 0],
        [
            ['type' => DegreeType::UNDERGRADUATE->value, 'major' => 'BBA'],
            ['type' => DegreeType::GRADUATE->value, 'major' => 'MBA'],
        ],
    );

    expect((int) app(ResultGenerationService::class)->generateForApplication($a17)->schooling_marks)
        ->toBe(5);
});

it('awards 0 for combinations outside the matrix', function () {
    // 18yr Master is not in the matrix.
    ['application' => $application] = makeApplicantWithProfile(
        ['tot_year_of_schooling' => 18, 'tot_year_of_exp' => 0],
        [['type' => DegreeType::GRADUATE->value, 'major' => 'MBA']],
    );

    expect((int) app(ResultGenerationService::class)->generateForApplication($application)->schooling_marks)
        ->toBe(0);
});

it('returns 0 schooling marks when no qualifying degree on file', function () {
    ['application' => $application] = makeApplicantWithProfile(
        ['tot_year_of_schooling' => 16, 'tot_year_of_exp' => 0],
        [['type' => DegreeType::HSC->value, 'major' => 'Science']],
    );

    expect((int) app(ResultGenerationService::class)->generateForApplication($application)->schooling_marks)
        ->toBe(0);
});

it('applies the experience curve: first two years free, +1 per year, cap at 10', function (float $years, int $expected) {
    ['application' => $application] = makeApplicantWithProfile(
        ['tot_year_of_schooling' => 0, 'tot_year_of_exp' => $years],
    );

    expect((int) app(ResultGenerationService::class)->generateForApplication($application)->experience_marks)
        ->toBe($expected);
})->with([
    '0 yr → 0' => [0.0, 0],
    '2 yr → 0' => [2.0, 0],
    '3 yr → 1' => [3.0, 1],
    '7 yr → 5' => [7.0, 5],
    '12 yr → 10' => [12.0, 10],
    '15 yr → 10 (capped)' => [15.0, 10],
    '2.9 yr → 0 (truncated)' => [2.9, 0],
]);

it('sets total_marks to schooling + experience and defaults the exam components to 0', function () {
    ['application' => $application] = makeApplicantWithProfile(
        ['tot_year_of_schooling' => 16, 'tot_year_of_exp' => 7],
        [['type' => DegreeType::UNDERGRADUATE->value, 'major' => 'BBA']],
    );

    $result = app(ResultGenerationService::class)->generateForApplication($application);

    expect((int) $result->schooling_marks)->toBe(4)
        ->and((int) $result->experience_marks)->toBe(5)
        ->and((int) $result->total_marks)->toBe(9)
        ->and((int) $result->mcq_marks)->toBe(0)
        ->and((int) $result->written_marks)->toBe(0)
        ->and((int) $result->viva_marks)->toBe(0)
        ->and($result->status)->toBe(ResultStatusEnum::FAILED);
});

it('is idempotent — re-running updates the same row instead of duplicating', function () {
    ['application' => $application] = makeApplicantWithProfile(
        ['tot_year_of_schooling' => 16, 'tot_year_of_exp' => 5],
        [['type' => DegreeType::UNDERGRADUATE->value, 'major' => 'BBA']],
    );

    $first = app(ResultGenerationService::class)->generateForApplication($application);
    $second = app(ResultGenerationService::class)->generateForApplication($application);

    expect($second->id)->toBe($first->id);
    expect(AdmissionResult::where('applicant_id', $application->applicant_id)->count())->toBe(1);
});

function seedResult(int $batchId, int $applicantNum, array $marks): AdmissionResult
{
    $applicant = Applicant::factory()->create(['batch_id' => $batchId]);

    return AdmissionResult::create(array_merge([
        'batch_id' => $batchId,
        'applicant_id' => $applicant->id,
        'application_number' => "APP-{$applicantNum}",
        'roll_number' => (string) (1000 + $applicantNum),
        'mcq_marks' => 0,
        'written_marks' => 0,
        'viva_marks' => 0,
        'schooling_marks' => 0,
        'experience_marks' => 0,
        'total_marks' => 0,
        'is_adjusted' => false,
        'status' => ResultStatusEnum::FAILED,
    ], $marks));
}

it('ranks merit by total_marks descending', function () {
    $batch = Batch::create([
        'name' => 'Merit T', 'code' => 'MT-'.uniqid(),
        'admission_year' => 2026, 'status' => BatchStatusEnum::OPEN,
    ]);

    $low = seedResult($batch->id, 1, ['total_marks' => 50]);
    $high = seedResult($batch->id, 2, ['total_marks' => 80]);
    $mid = seedResult($batch->id, 3, ['total_marks' => 65]);

    $count = app(ResultGenerationService::class)->generateMeritList($batch);

    expect($count)->toBe(3);
    expect($high->fresh()->merit_position)->toBe(1);
    expect($mid->fresh()->merit_position)->toBe(2);
    expect($low->fresh()->merit_position)->toBe(3);
});

it('walks the full tiebreaker chain: mcq → exp → written → schooling → viva', function () {
    $batch = Batch::create([
        'name' => 'Tie T', 'code' => 'TT-'.uniqid(),
        'admission_year' => 2026, 'status' => BatchStatusEnum::OPEN,
    ]);

    // All five candidates have identical total_marks (70). Each subsequent
    // candidate edges the previous out by exactly one tiebreaker.
    $a = seedResult($batch->id, 1, [
        'total_marks' => 70, 'mcq_marks' => 40, 'experience_marks' => 5,
        'written_marks' => 15, 'schooling_marks' => 3, 'viva_marks' => 1,
    ]);
    $b = seedResult($batch->id, 2, [
        'total_marks' => 70, 'mcq_marks' => 45, 'experience_marks' => 2,
        'written_marks' => 12, 'schooling_marks' => 2, 'viva_marks' => 0,
    ]); // higher mcq → beats $a
    $c = seedResult($batch->id, 3, [
        'total_marks' => 70, 'mcq_marks' => 45, 'experience_marks' => 10,
        'written_marks' => 10, 'schooling_marks' => 0, 'viva_marks' => 0,
    ]); // same mcq as $b, higher exp → beats $b
    $d = seedResult($batch->id, 4, [
        'total_marks' => 70, 'mcq_marks' => 45, 'experience_marks' => 10,
        'written_marks' => 20, 'schooling_marks' => 0, 'viva_marks' => 0,
    ]); // same mcq+exp as $c, higher written → beats $c
    $e = seedResult($batch->id, 5, [
        'total_marks' => 70, 'mcq_marks' => 45, 'experience_marks' => 10,
        'written_marks' => 20, 'schooling_marks' => 5, 'viva_marks' => 0,
    ]); // same mcq+exp+written, higher schooling → beats $d

    app(ResultGenerationService::class)->generateMeritList($batch);

    expect($e->fresh()->merit_position)->toBe(1);
    expect($d->fresh()->merit_position)->toBe(2);
    expect($c->fresh()->merit_position)->toBe(3);
    expect($b->fresh()->merit_position)->toBe(4);
    expect($a->fresh()->merit_position)->toBe(5);
});

it('falls back to viva_marks when all earlier columns are equal', function () {
    $batch = Batch::create([
        'name' => 'Viva T', 'code' => 'VT-'.uniqid(),
        'admission_year' => 2026, 'status' => BatchStatusEnum::OPEN,
    ]);

    $base = [
        'total_marks' => 60, 'mcq_marks' => 30, 'experience_marks' => 5,
        'written_marks' => 15, 'schooling_marks' => 5,
    ];

    $lowViva = seedResult($batch->id, 1, $base + ['viva_marks' => 1]);
    $highViva = seedResult($batch->id, 2, $base + ['viva_marks' => 4]);

    app(ResultGenerationService::class)->generateMeritList($batch);

    expect($highViva->fresh()->merit_position)->toBe(1);
    expect($lowViva->fresh()->merit_position)->toBe(2);
});

it('returns 0 and assigns nothing when the batch has no results', function () {
    $batch = Batch::create([
        'name' => 'Empty T', 'code' => 'ET-'.uniqid(),
        'admission_year' => 2026, 'status' => BatchStatusEnum::OPEN,
    ]);

    expect(app(ResultGenerationService::class)->generateMeritList($batch))->toBe(0);
});

it('is idempotent — re-running yields the same merit positions', function () {
    $batch = Batch::create([
        'name' => 'Idemp T', 'code' => 'IT-'.uniqid(),
        'admission_year' => 2026, 'status' => BatchStatusEnum::OPEN,
    ]);

    $low = seedResult($batch->id, 1, ['total_marks' => 40]);
    $high = seedResult($batch->id, 2, ['total_marks' => 90]);

    app(ResultGenerationService::class)->generateMeritList($batch);
    app(ResultGenerationService::class)->generateMeritList($batch);

    expect($high->fresh()->merit_position)->toBe(1);
    expect($low->fresh()->merit_position)->toBe(2);
});

it('skips merit ranking for candidates below the passing mark', function () {
    $batch = Batch::create([
        'name' => 'Fail T', 'code' => 'FT-'.uniqid(),
        'admission_year' => 2026, 'status' => BatchStatusEnum::OPEN,
    ]);

    $passingMarks = (int) config('result.passing_marks'); // 40

    $failedLow = seedResult($batch->id, 1, ['total_marks' => $passingMarks - 1]);
    $passedTop = seedResult($batch->id, 2, ['total_marks' => 85]);
    $failedZero = seedResult($batch->id, 3, ['total_marks' => 0]);
    $passedBoundary = seedResult($batch->id, 4, ['total_marks' => $passingMarks]);

    $ranked = app(ResultGenerationService::class)->generateMeritList($batch);

    expect($ranked)->toBe(2);

    expect($passedTop->fresh()->merit_position)->toBe(1);
    expect($passedTop->fresh()->status)->toBe(ResultStatusEnum::PASSED);

    expect($passedBoundary->fresh()->merit_position)->toBe(2);
    expect($passedBoundary->fresh()->status)->toBe(ResultStatusEnum::PASSED);

    expect($failedLow->fresh()->merit_position)->toBeNull();
    expect($failedLow->fresh()->status)->toBe(ResultStatusEnum::FAILED);

    expect($failedZero->fresh()->merit_position)->toBeNull();
    expect($failedZero->fresh()->status)->toBe(ResultStatusEnum::FAILED);
});

it('uses the batch admission setting pass mark over the config fallback', function () {
    $batch = Batch::create([
        'name' => 'PassMark T', 'code' => 'PM-'.uniqid(),
        'admission_year' => 2026, 'status' => BatchStatusEnum::OPEN,
    ]);

    AdmissionSetting::create([
        'batch_id' => $batch->id,
        'pass_mark' => 50,
    ]);

    // 45 clears the config default (40) but falls short of this batch's 50.
    $below = seedResult($batch->id, 1, ['total_marks' => 45]);
    $atCutoff = seedResult($batch->id, 2, ['total_marks' => 50]);
    $above = seedResult($batch->id, 3, ['total_marks' => 60]);

    $ranked = app(ResultGenerationService::class)->generateMeritList($batch);

    expect($ranked)->toBe(2);
    expect($above->fresh()->merit_position)->toBe(1);
    expect($atCutoff->fresh()->merit_position)->toBe(2);
    expect($below->fresh()->merit_position)->toBeNull();
    expect($below->fresh()->status)->toBe(ResultStatusEnum::FAILED);
});

it('falls back to the config pass mark when the batch has no admission setting', function () {
    $batch = Batch::create([
        'name' => 'NoSetting T', 'code' => 'NS-'.uniqid(),
        'admission_year' => 2026, 'status' => BatchStatusEnum::OPEN,
    ]);

    // 45 ≥ config('result.passing_marks') (40), so it passes without a per-batch override.
    $candidate = seedResult($batch->id, 1, ['total_marks' => 45]);

    app(ResultGenerationService::class)->generateMeritList($batch);

    expect($candidate->fresh()->status)->toBe(ResultStatusEnum::PASSED);
    expect($candidate->fresh()->merit_position)->toBe(1);
});

it('clears a previously-assigned merit_position when marks drop below the cutoff', function () {
    $batch = Batch::create([
        'name' => 'Drop T', 'code' => 'DT-'.uniqid(),
        'admission_year' => 2026, 'status' => BatchStatusEnum::OPEN,
    ]);

    $candidate = seedResult($batch->id, 1, ['total_marks' => 60]);

    app(ResultGenerationService::class)->generateMeritList($batch);
    expect($candidate->fresh()->merit_position)->toBe(1);
    expect($candidate->fresh()->status)->toBe(ResultStatusEnum::PASSED);

    // Mark gets adjusted downward below the cutoff — re-rank.
    $candidate->update(['total_marks' => 20]);
    app(ResultGenerationService::class)->generateMeritList($batch);

    expect($candidate->fresh()->merit_position)->toBeNull();
    expect($candidate->fresh()->status)->toBe(ResultStatusEnum::FAILED);
});
