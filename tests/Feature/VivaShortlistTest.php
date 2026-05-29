<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\ResultStatusEnum;
use App\Models\AdmissionResult;
use App\Models\AdmissionSetting;
use App\Models\Applicant;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\Batch;
use App\Models\User;
use App\Models\VivaBoard;
use App\Support\CurrentBatch;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->batch = Batch::create([
        'name' => 'EMBA Viva '.uniqid(),
        'code' => 'EMBA-VV-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    CurrentBatch::set($this->batch->id);
});

function makeVivaResult(Batch $batch, string $roll, float $mcq, string $name): AdmissionResult
{
    $applicant = Applicant::factory()->create(['batch_id' => $batch->id]);

    ApplicantProfile::create([
        'applicant_id' => $applicant->id,
        'batch_id' => $batch->id,
        'full_name' => $name,
        'father_name' => 'F',
        'mother_name' => 'M',
        'date_of_birth' => '1990-01-01',
        'tot_year_of_schooling' => 16,
        'tot_year_of_exp' => 5,
    ]);

    return AdmissionResult::create([
        'batch_id' => $batch->id,
        'applicant_id' => $applicant->id,
        'application_number' => $batch->code.'-'.$roll,
        'roll_number' => $roll,
        'mcq_marks' => $mcq,
        'written_marks' => 0,
        'viva_marks' => 0,
        'schooling_marks' => 4,
        'experience_marks' => 5,
        'total_marks' => 9 + $mcq,
        'is_adjusted' => false,
        'status' => ResultStatusEnum::FAILED,
    ]);
}

it('lists only candidates at or above the MCQ cutoff', function () {
    AdmissionSetting::create(['batch_id' => $this->batch->id, 'viva_mcq_threshold' => 25]);

    makeVivaResult($this->batch, '1000', 30, 'Eligible Top');   // ≥ 25 → in
    makeVivaResult($this->batch, '1001', 25, 'Eligible Edge');  // == 25 → in
    makeVivaResult($this->batch, '1002', 24, 'Below Cutoff');   // < 25 → out

    Livewire::test('pages::admin.viva-shortlist')
        ->assertViewHas('eligibleCount', 2)
        ->assertViewHas('totalCount', 3)
        ->assertSee('Eligible Top')
        ->assertSee('Eligible Edge')
        ->assertDontSee('Below Cutoff');
});

it('respects the per-batch threshold', function () {
    AdmissionSetting::create(['batch_id' => $this->batch->id, 'viva_mcq_threshold' => 40]);

    makeVivaResult($this->batch, '1000', 45, 'High Scorer');  // ≥ 40 → in
    makeVivaResult($this->batch, '1001', 30, 'Mid Scorer');   // < 40 → out

    Livewire::test('pages::admin.viva-shortlist')
        ->assertViewHas('eligibleCount', 1)
        ->assertSee('High Scorer')
        ->assertDontSee('Mid Scorer');
});

it('falls back to the config threshold when the batch has no admission setting', function () {
    // No AdmissionSetting → uses config('result.viva_mcq_threshold') (25).
    makeVivaResult($this->batch, '1000', 26, 'Config Eligible');
    makeVivaResult($this->batch, '1001', 10, 'Config Ineligible');

    Livewire::test('pages::admin.viva-shortlist')
        ->assertViewHas('threshold', (float) config('result.viva_mcq_threshold'))
        ->assertViewHas('eligibleCount', 1)
        ->assertSee('Config Eligible')
        ->assertDontSee('Config Ineligible');
});

it('shows the assigned viva board for a shortlisted candidate', function () {
    AdmissionSetting::create(['batch_id' => $this->batch->id, 'viva_mcq_threshold' => 25]);

    $assigned = makeVivaResult($this->batch, '1000', 30, 'Boarded Candidate');
    $unassigned = makeVivaResult($this->batch, '1001', 28, 'Unboarded Candidate');

    $board = VivaBoard::create(['batch_id' => $this->batch->id, 'board_name' => 'Board Alpha']);

    Application::create([
        'applicant_id' => $assigned->applicant_id,
        'batch_id' => $this->batch->id,
        'application_number' => $this->batch->code.'-1000',
        'roll_number' => '1000',
        'status' => ApplicationStatusEnum::COMPLETED,
        'payment_status' => PaymentStatusEnum::PAID,
        'applied_at' => now(),
        'viva_board_id' => $board->id,
    ]);

    // Unassigned candidate has an application but no board.
    Application::create([
        'applicant_id' => $unassigned->applicant_id,
        'batch_id' => $this->batch->id,
        'application_number' => $this->batch->code.'-1001',
        'roll_number' => '1001',
        'status' => ApplicationStatusEnum::COMPLETED,
        'payment_status' => PaymentStatusEnum::PAID,
        'applied_at' => now(),
    ]);

    Livewire::test('pages::admin.viva-shortlist')
        ->assertSee('Boarded Candidate')
        ->assertSee('Unboarded Candidate')
        ->assertSee('Board Alpha');
});

it('filters the shortlist by search term', function () {
    AdmissionSetting::create(['batch_id' => $this->batch->id, 'viva_mcq_threshold' => 25]);

    makeVivaResult($this->batch, '1000', 30, 'Alice Rahman');
    makeVivaResult($this->batch, '1001', 30, 'Bob Karim');

    Livewire::test('pages::admin.viva-shortlist')
        ->set('search', 'Alice')
        ->assertSee('Alice Rahman')
        ->assertDontSee('Bob Karim');
});
