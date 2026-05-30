<?php

use App\Enums\ResultStatusEnum;
use App\Models\AdmissionResult;
use App\Models\AdmissionSetting;
use App\Models\Applicant;
use App\Models\Batch;

use function Pest\Laravel\actingAs;

function makeApplicantWithResult(bool $published, ResultStatusEnum $status, array $overrides = []): Applicant
{
    $batch = Batch::factory()->create();

    AdmissionSetting::create([
        'batch_id' => $batch->id,
        'result_published_at' => $published ? now() : null,
    ]);

    $applicant = Applicant::factory()->create(['batch_id' => $batch->id]);

    AdmissionResult::create(array_merge([
        'batch_id' => $batch->id,
        'applicant_id' => $applicant->id,
        'application_number' => 'APP-'.$applicant->id,
        'roll_number' => 'ROLL-'.$applicant->id,
        'mcq_marks' => 40,
        'written_marks' => 18,
        'viva_marks' => 4,
        'schooling_marks' => 5,
        'experience_marks' => 8,
        'total_marks' => 75,
        'merit_position' => $status === ResultStatusEnum::PASSED ? 3 : null,
        'status' => $status,
    ], $overrides));

    return $applicant;
}

it('hides the result when it is not published', function () {
    $applicant = makeApplicantWithResult(published: false, status: ResultStatusEnum::PASSED);

    actingAs($applicant, 'applicant');

    $this->get(route('applicant.result'))
        ->assertOk()
        ->assertSee('not published yet')
        ->assertDontSee('Mark breakdown');
});

it('shows a passed result with merit position once published', function () {
    $applicant = makeApplicantWithResult(published: true, status: ResultStatusEnum::PASSED);

    actingAs($applicant, 'applicant');

    $this->get(route('applicant.result'))
        ->assertOk()
        ->assertSee('Congratulations')
        ->assertSee('Merit Position: 3')
        ->assertSee('Mark breakdown')
        ->assertSee(ResultStatusEnum::PASSED->label());
});

it('shows a failed result once published', function () {
    $applicant = makeApplicantWithResult(published: true, status: ResultStatusEnum::FAILED);

    actingAs($applicant, 'applicant');

    $this->get(route('applicant.result'))
        ->assertOk()
        ->assertSee('not been selected')
        ->assertSee(ResultStatusEnum::FAILED->label())
        ->assertDontSee('Merit Position');
});

it('redirects a guest to login', function () {
    $this->get(route('applicant.result'))->assertRedirect(route('applicant.login'));
});
