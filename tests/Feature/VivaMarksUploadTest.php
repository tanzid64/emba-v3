<?php

use App\Enum\BatchStatusEnum;
use App\Enums\DegreeType;
use App\Enums\ResultStatusEnum;
use App\Models\AdmissionResult;
use App\Models\AdmissionSetting;
use App\Models\Applicant;
use App\Models\Batch;
use App\Models\EducationHistory;
use App\Models\User;
use App\Services\VivaMarksUploadService;
use App\Support\CurrentBatch;
use Illuminate\Http\Testing\File;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->batch = Batch::create([
        'name' => 'EMBA Viva Marks '.uniqid(),
        'code' => 'EMBA-VM-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    AdmissionSetting::create(['batch_id' => $this->batch->id]);

    CurrentBatch::set($this->batch->id);
});

/**
 * Seed a result with a Graduate degree on file (so the schooling matrix
 * resolves) plus pre-exam marks. Defaults: schooling 4 (16yr Master),
 * experience 5, mcq 40, written 20.
 */
function seedVivaResult(Batch $batch, string $roll, array $overrides = [], DegreeType $degree = DegreeType::GRADUATE): AdmissionResult
{
    $applicant = Applicant::factory()->create(['batch_id' => $batch->id]);

    EducationHistory::create([
        'applicant_id' => $applicant->id,
        'type' => $degree,
        'name' => 'Master of Business',
        'major' => 'Management',
        'institute' => 'University of Dhaka',
        'result' => '3.50',
        'scale' => '4.00',
    ]);

    return AdmissionResult::create(array_merge([
        'batch_id' => $batch->id,
        'applicant_id' => $applicant->id,
        'application_number' => $batch->code.'-'.$roll,
        'roll_number' => $roll,
        'mcq_marks' => 40,
        'written_marks' => 20,
        'viva_marks' => 0,
        'schooling_marks' => 4,
        'experience_marks' => 5,
        'total_marks' => 69,
        'is_adjusted' => false,
        'status' => ResultStatusEnum::FAILED,
    ], $overrides));
}

function writeVivaCsv(array $rows, array $headers = ['roll_number', 'viva', 'schooling_years', 'experience_years']): string
{
    $path = tempnam(sys_get_temp_dir(), 'vcsv').'.csv';
    $f = fopen($path, 'w');
    fputcsv($f, $headers);
    foreach ($rows as $r) {
        fputcsv($f, $r);
    }
    fclose($f);

    return $path;
}

it('re-derives marks from years, overrides on a difference, and flags is_adjusted', function () {
    $result = seedVivaResult($this->batch, '1000'); // schooling 4 (16yr), experience 5

    // viva 5; schooling 17yr -> 5 (differs from 4); experience 9yr -> 7 (differs from 5)
    $csv = writeVivaCsv([['1000', 5, 17, 9]]);

    $summary = app(VivaMarksUploadService::class)->import($this->batch, $csv);

    expect($summary)->toBe(['rows' => 1, 'updated' => 1, 'unchanged' => 0, 'adjusted' => 1]);

    $result->refresh();
    expect((float) $result->viva_marks)->toBe(5.0);
    expect((float) $result->schooling_marks)->toBe(5.0);
    expect((float) $result->experience_marks)->toBe(7.0);
    expect($result->is_adjusted)->toBeTrue();
    // total = mcq 40 + written 20 + viva 5 + schooling 5 + experience 7
    expect((float) $result->total_marks)->toBe(77.0);
});

it('does not flag is_adjusted when recomputed marks match the current values', function () {
    $result = seedVivaResult($this->batch, '1000', ['experience_marks' => 7, 'total_marks' => 71]);

    // schooling 16yr -> 4 (== current), experience 9yr -> 7 (== current); only viva changes
    $csv = writeVivaCsv([['1000', 5, 16, 9]]);

    $summary = app(VivaMarksUploadService::class)->import($this->batch, $csv);

    expect($summary['adjusted'])->toBe(0);
    expect($summary['updated'])->toBe(1); // viva changed

    $result->refresh();
    expect((float) $result->viva_marks)->toBe(5.0);
    expect($result->is_adjusted)->toBeFalse();
    expect((float) $result->schooling_marks)->toBe(4.0);
    expect((float) $result->experience_marks)->toBe(7.0);
});

it('updates only the viva mark when schooling/experience cells are blank', function () {
    $result = seedVivaResult($this->batch, '1000');

    $csv = writeVivaCsv([['1000', 4, '', '']]);

    $summary = app(VivaMarksUploadService::class)->import($this->batch, $csv);

    expect($summary)->toBe(['rows' => 1, 'updated' => 1, 'unchanged' => 0, 'adjusted' => 0]);

    $result->refresh();
    expect((float) $result->viva_marks)->toBe(4.0);
    expect((float) $result->schooling_marks)->toBe(4.0);
    expect((float) $result->experience_marks)->toBe(5.0);
    expect($result->is_adjusted)->toBeFalse();
});

it('keeps the existing viva mark when the viva cell is blank but still adjusts schooling', function () {
    $result = seedVivaResult($this->batch, '1000', ['viva_marks' => 3]);

    // viva blank; schooling 17yr -> 5 (differs from 4); experience blank
    $csv = writeVivaCsv([['1000', '', 17, '']]);

    $summary = app(VivaMarksUploadService::class)->import($this->batch, $csv);

    expect($summary['adjusted'])->toBe(1);

    $result->refresh();
    expect((float) $result->viva_marks)->toBe(3.0); // untouched
    expect((float) $result->schooling_marks)->toBe(5.0);
    expect($result->is_adjusted)->toBeTrue();
});

it('throws when a roll number has no result row in the batch', function () {
    seedVivaResult($this->batch, '1000');

    $csv = writeVivaCsv([['9999', 5, 16, 9]]);

    expect(fn () => app(VivaMarksUploadService::class)->import($this->batch, $csv))
        ->toThrow(RuntimeException::class, 'no result row');
});

it('rejects a viva mark above the configured maximum', function () {
    seedVivaResult($this->batch, '1000');

    $csv = writeVivaCsv([['1000', config('result.max_viva_marks') + 1, 16, 9]]);

    expect(fn () => app(VivaMarksUploadService::class)->import($this->batch, $csv))
        ->toThrow(RuntimeException::class, 'viva');
});

it('rejects duplicate roll numbers within the CSV', function () {
    seedVivaResult($this->batch, '1000');

    $csv = writeVivaCsv([['1000', 5, 16, 9], ['1000', 4, 17, 5]]);

    expect(fn () => app(VivaMarksUploadService::class)->import($this->batch, $csv))
        ->toThrow(RuntimeException::class, 'Duplicate roll number');
});

it('rejects a CSV missing a required column', function () {
    seedVivaResult($this->batch, '1000');

    $csv = writeVivaCsv([['1000', 5, 16]], headers: ['roll_number', 'viva', 'schooling_years']);

    expect(fn () => app(VivaMarksUploadService::class)->import($this->batch, $csv))
        ->toThrow(RuntimeException::class, 'Missing required CSV column');
});

it('imports viva marks through the Livewire action and auto-runs the merit list', function () {
    $result = seedVivaResult($this->batch, '1000'); // mcq 40 + written 20 + schooling 4 + experience 5

    $csv = File::createWithContent(
        'viva.csv',
        "roll_number,viva,schooling_years,experience_years\n1000,5,17,9\n"
    );

    Livewire::test('pages::admin.exam-results')
        ->set('vivaCsv', $csv)
        ->call('uploadVivaMarks')
        ->assertHasNoErrors();

    $result->refresh();
    expect($result->is_adjusted)->toBeTrue();
    expect((float) $result->total_marks)->toBe(77.0); // 40 + 20 + 5 + 5 + 7
    expect($result->status)->toBe(ResultStatusEnum::PASSED);
    expect($result->merit_position)->toBe(1);
});
