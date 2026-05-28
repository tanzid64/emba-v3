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

beforeEach(function () {
    $this->batch = Batch::create([
        'name' => 'EMBA Export '.uniqid(),
        'code' => 'EMBA-EX-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    AdmissionSetting::create(['batch_id' => $this->batch->id, 'viva_mcq_threshold' => 25]);
});

function makeExportApplicant(Batch $batch, string $roll, float $mcq): void
{
    $applicant = Applicant::factory()->create(['batch_id' => $batch->id]);

    ApplicantProfile::create([
        'applicant_id' => $applicant->id,
        'batch_id' => $batch->id,
        'full_name' => 'Candidate '.$roll,
        'father_name' => 'Father '.$roll,
        'mother_name' => 'Mother '.$roll,
        'date_of_birth' => '1990-01-01',
        'tot_year_of_schooling' => 16,
        'tot_year_of_exp' => 5,
    ]);

    Application::create([
        'applicant_id' => $applicant->id,
        'batch_id' => $batch->id,
        'application_number' => $batch->code.'-'.$roll,
        'roll_number' => $roll,
        'status' => ApplicationStatusEnum::COMPLETED,
        'payment_status' => PaymentStatusEnum::PAID,
        'applied_at' => now(),
        'paid_at' => now(),
    ]);

    AdmissionResult::create([
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

it('lets an admin download the confirmed applicants Excel', function () {
    makeExportApplicant($this->batch, '1000', 30);

    $this->actingAs(User::factory()->create(), 'web')
        ->get(route('excel.confirmed-applicants', $this->batch))
        ->assertOk()
        ->assertDownload();
});

it('lets an admin download the confirmed applicants PDF', function () {
    makeExportApplicant($this->batch, '1000', 30);

    $this->actingAs(User::factory()->create(), 'web')
        ->get(route('pdf.confirmed-applicants', $this->batch))
        ->assertOk()
        ->assertDownload();
});

it('lets an admin download the viva shortlist Excel', function () {
    makeExportApplicant($this->batch, '1000', 30);

    $this->actingAs(User::factory()->create(), 'web')
        ->get(route('excel.viva-shortlist', $this->batch))
        ->assertOk()
        ->assertDownload();
});

it('lets an admin download the viva shortlist PDF', function () {
    makeExportApplicant($this->batch, '1000', 30);

    $this->actingAs(User::factory()->create(), 'web')
        ->get(route('pdf.viva-shortlist', $this->batch))
        ->assertOk()
        ->assertDownload();
});

it('blocks guests from the exports', function () {
    $this->get(route('excel.confirmed-applicants', $this->batch))->assertStatus(302);
    $this->get(route('pdf.viva-shortlist', $this->batch))->assertStatus(302);
});
