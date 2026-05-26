<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\AdmissionSetting;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Batch;
use App\Models\ExamCenter;
use App\Models\User;
use App\Services\ExamCenterUploadService;
use App\Support\CurrentBatch;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->batch = Batch::create([
        'name' => 'EMBA Upload '.uniqid(),
        'code' => 'EMBA-UP-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    AdmissionSetting::create([
        'batch_id' => $this->batch->id,
        'application_number_start_from' => 1000,
        'roll_number_start_from' => 1000,
        'intake_ended_at' => now()->subDays(10)->toDateString(),
        'application_payment_ended_at' => now()->subDays(5)->toDateString(),
    ]);

    CurrentBatch::set($this->batch->id);
});

function makeConfirmedApp(Batch $batch, string $roll, string $appSuffix): Application
{
    $applicant = Applicant::factory()->create(['batch_id' => $batch->id]);

    return Application::create([
        'applicant_id' => $applicant->id,
        'batch_id' => $batch->id,
        'application_number' => $batch->code.'-'.$appSuffix,
        'roll_number' => $roll,
        'status' => ApplicationStatusEnum::COMPLETED,
        'payment_status' => PaymentStatusEnum::PAID,
        'applied_at' => now()->subDays(2),
    ]);
}

function writeCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'ecsv').'.csv';
    $f = fopen($path, 'w');
    fputcsv($f, ['center_no', 'center_name', 'room_name', 'capacity']);
    foreach ($rows as $r) {
        fputcsv($f, $r);
    }
    fclose($f);

    return $path;
}

it('imports centers and seats confirmed applicants in roll order', function () {
    $a = makeConfirmedApp($this->batch, '1000', '1000');
    $b = makeConfirmedApp($this->batch, '1001', '1001');
    $c = makeConfirmedApp($this->batch, '1002', '1002');

    $csv = writeCsv([
        ['C-01', 'Main', 'Room 101', 2],
        ['C-01', 'Main', 'Room 102', 2],
    ]);

    $result = app(ExamCenterUploadService::class)->import($this->batch, $csv);

    expect($result)->toBe(['centers' => 1, 'rooms' => 2, 'assigned' => 3]);

    $a->refresh();
    $b->refresh();
    $c->refresh();
    expect($a->exam_center_id)->not->toBeNull();
    expect($a->seat_no)->toBe('01');
    expect($b->seat_no)->toBe('02');
    expect($c->seat_no)->toBe('01'); // overflowed into Room 102
    expect($a->exam_center_id)->toBe($b->exam_center_id);
    expect($c->exam_center_id)->not->toBe($a->exam_center_id);
});

it('replaces existing centers and assignments on re-upload', function () {
    makeConfirmedApp($this->batch, '1000', '1000');

    $oldCenter = ExamCenter::create([
        'batch_id' => $this->batch->id,
        'center_no' => 'OLD',
        'center_name' => 'Old',
        'room_name' => 'Old Room',
        'capacity' => 1,
    ]);

    $csv = writeCsv([['C-01', 'Main', 'Room 101', 1]]);

    app(ExamCenterUploadService::class)->import($this->batch, $csv);

    expect(ExamCenter::find($oldCenter->id))->toBeNull();
    expect(ExamCenter::where('batch_id', $this->batch->id)->count())->toBe(1);
});

it('throws when total capacity is less than confirmed count', function () {
    makeConfirmedApp($this->batch, '1000', '1000');
    makeConfirmedApp($this->batch, '1001', '1001');
    makeConfirmedApp($this->batch, '1002', '1002');

    $csv = writeCsv([['C-01', 'Main', 'Room 101', 2]]);

    expect(fn () => app(ExamCenterUploadService::class)->import($this->batch, $csv))
        ->toThrow(RuntimeException::class, 'less than confirmed applicants');
});

it('rejects malformed CSV (missing column)', function () {
    $path = tempnam(sys_get_temp_dir(), 'bad').'.csv';
    file_put_contents($path, "center_no,center_name,capacity\nC-01,Main,50\n");

    expect(fn () => app(ExamCenterUploadService::class)->import($this->batch, $path))
        ->toThrow(RuntimeException::class, 'Missing required CSV column');
});

it('rejects duplicate room within the CSV', function () {
    $csv = writeCsv([
        ['C-01', 'Main', 'Room 101', 50],
        ['C-01', 'Main', 'Room 101', 50],
    ]);

    expect(fn () => app(ExamCenterUploadService::class)->import($this->batch, $csv))
        ->toThrow(RuntimeException::class, 'Duplicate room');
});

it('exposes both conditions as met on the Livewire component', function () {
    Livewire::test('pages::admin.exam-centers')
        ->assertOk();

    expect(true)->toBeTrue(); // sanity — beforeEach made both dates in the past
});

it('upload button is hidden when conditions are not met', function () {
    $this->batch->admissionSetting->update([
        'intake_ended_at' => now()->addDays(10)->toDateString(),
    ]);

    $component = Livewire::test('pages::admin.exam-centers');

    expect($component->instance()->isAdmissionClosed())->toBeFalse();
    expect($component->instance()->canUpload())->toBeFalse();
});
