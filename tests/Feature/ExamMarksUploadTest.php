<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ResultStatusEnum;
use App\Models\AdmissionResult;
use App\Models\AdmissionSetting;
use App\Models\Applicant;
use App\Models\Batch;
use App\Models\User;
use App\Services\ExamMarksUploadService;
use App\Support\CurrentBatch;
use Illuminate\Http\Testing\File;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->batch = Batch::create([
        'name' => 'EMBA Marks '.uniqid(),
        'code' => 'EMBA-MK-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    AdmissionSetting::create(['batch_id' => $this->batch->id]);

    CurrentBatch::set($this->batch->id);
});

function seedMarkResult(Batch $batch, string $roll, array $overrides = []): AdmissionResult
{
    $applicant = Applicant::factory()->create(['batch_id' => $batch->id]);

    return AdmissionResult::create(array_merge([
        'batch_id' => $batch->id,
        'applicant_id' => $applicant->id,
        'application_number' => $batch->code.'-'.$roll,
        'roll_number' => $roll,
        'mcq_marks' => 0,
        'written_marks' => 0,
        'viva_marks' => 0,
        'schooling_marks' => 4,
        'experience_marks' => 5,
        'total_marks' => 9, // schooling + experience snapshot
        'is_adjusted' => false,
        'status' => ResultStatusEnum::FAILED,
    ], $overrides));
}

function writeMarksCsv(array $rows, array $headers = ['roll_number', 'mcq', 'written']): string
{
    $path = tempnam(sys_get_temp_dir(), 'mcsv').'.csv';
    $f = fopen($path, 'w');
    fputcsv($f, $headers);
    foreach ($rows as $r) {
        fputcsv($f, $r);
    }
    fclose($f);

    return $path;
}

it('applies mcq and written marks and recomputes total', function () {
    $result = seedMarkResult($this->batch, '1000'); // schooling 4 + experience 5

    $csv = writeMarksCsv([['1000', 50, 20]]);

    $summary = app(ExamMarksUploadService::class)->import($this->batch, $csv);

    expect($summary)->toBe(['rows' => 1, 'updated' => 1, 'unchanged' => 0]);

    $result->refresh();
    expect((float) $result->mcq_marks)->toBe(50.0);
    expect((float) $result->written_marks)->toBe(20.0);
    expect((float) $result->total_marks)->toBe(79.0); // 4 + 5 + 0 viva + 50 + 20
});

it('leaves a component unchanged when its cell is blank (mcq-first upload)', function () {
    $result = seedMarkResult($this->batch, '1000', ['written_marks' => 11, 'total_marks' => 20]);

    $csv = writeMarksCsv([['1000', 50, '']]); // only MCQ provided

    app(ExamMarksUploadService::class)->import($this->batch, $csv);

    $result->refresh();
    expect((float) $result->mcq_marks)->toBe(50.0);
    expect((float) $result->written_marks)->toBe(11.0); // untouched
    expect((float) $result->total_marks)->toBe(70.0); // 4 + 5 + 0 + 50 + 11
});

it('counts a row with both cells blank as unchanged', function () {
    $result = seedMarkResult($this->batch, '1000');

    $csv = writeMarksCsv([['1000', '', '']]);

    $summary = app(ExamMarksUploadService::class)->import($this->batch, $csv);

    expect($summary)->toBe(['rows' => 1, 'updated' => 0, 'unchanged' => 1]);
    expect((float) $result->fresh()->mcq_marks)->toBe(0.0);
});

it('throws when a roll number has no result row in the batch', function () {
    seedMarkResult($this->batch, '1000');

    $csv = writeMarksCsv([['9999', 50, 20]]);

    expect(fn () => app(ExamMarksUploadService::class)->import($this->batch, $csv))
        ->toThrow(RuntimeException::class, 'no result row');
});

it('rejects an mcq mark above the configured maximum', function () {
    seedMarkResult($this->batch, '1000');

    $csv = writeMarksCsv([['1000', config('result.max_mcq_marks') + 1, 20]]);

    expect(fn () => app(ExamMarksUploadService::class)->import($this->batch, $csv))
        ->toThrow(RuntimeException::class, 'MCQ mark');
});

it('rejects a written mark above the configured maximum', function () {
    seedMarkResult($this->batch, '1000');

    $csv = writeMarksCsv([['1000', 40, config('result.max_written_marks') + 1]]);

    expect(fn () => app(ExamMarksUploadService::class)->import($this->batch, $csv))
        ->toThrow(RuntimeException::class, 'written mark');
});

it('rejects a non-numeric mark', function () {
    seedMarkResult($this->batch, '1000');

    $csv = writeMarksCsv([['1000', 'abc', 20]]);

    expect(fn () => app(ExamMarksUploadService::class)->import($this->batch, $csv))
        ->toThrow(RuntimeException::class, 'non-numeric');
});

it('rejects a CSV missing a required column', function () {
    seedMarkResult($this->batch, '1000');

    $csv = writeMarksCsv([['1000', 50]], headers: ['roll_number', 'mcq']);

    expect(fn () => app(ExamMarksUploadService::class)->import($this->batch, $csv))
        ->toThrow(RuntimeException::class, 'Missing required CSV column');
});

it('rejects duplicate roll numbers within the CSV', function () {
    seedMarkResult($this->batch, '1000');

    $csv = writeMarksCsv([['1000', 50, 20], ['1000', 40, 15]]);

    expect(fn () => app(ExamMarksUploadService::class)->import($this->batch, $csv))
        ->toThrow(RuntimeException::class, 'Duplicate roll number');
});

it('is all-or-nothing — one bad row aborts the whole import', function () {
    $good = seedMarkResult($this->batch, '1000');
    seedMarkResult($this->batch, '1001');

    // Second row references an unknown roll, so nothing should persist.
    $csv = writeMarksCsv([['1000', 50, 20], ['9999', 40, 15]]);

    expect(fn () => app(ExamMarksUploadService::class)->import($this->batch, $csv))
        ->toThrow(RuntimeException::class);

    expect((float) $good->fresh()->mcq_marks)->toBe(0.0);
});

it('surfaces a parse error as a validation error on the Livewire component', function () {
    seedMarkResult($this->batch, '1000');

    $csv = File::createWithContent(
        'marks.csv',
        "roll_number,mcq,written\n9999,50,20\n"
    );

    Livewire::test('pages::admin.exam-results')
        ->set('csv', $csv)
        ->call('uploadMarks')
        ->assertHasErrors(['csv']);

    expect((float) AdmissionResult::where('roll_number', '1000')->first()->mcq_marks)->toBe(0.0);
});

it('imports marks through the Livewire upload action and auto-runs the merit list', function () {
    $result = seedMarkResult($this->batch, '1000'); // schooling 4 + experience 5, pass mark defaults to 40

    $csv = File::createWithContent(
        'marks.csv',
        "roll_number,mcq,written\n1000,50,20\n"
    );

    Livewire::test('pages::admin.exam-results')
        ->set('csv', $csv)
        ->call('uploadMarks')
        ->assertHasNoErrors();

    $result->refresh();
    expect((float) $result->total_marks)->toBe(79.0);
    // Merit list ran automatically: 79 ≥ pass mark, so the row is ranked + PASSED.
    expect($result->merit_position)->toBe(1);
    expect($result->status)->toBe(ResultStatusEnum::PASSED);
});
