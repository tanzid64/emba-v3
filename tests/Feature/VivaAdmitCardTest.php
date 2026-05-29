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
    $this->batch = Batch::create([
        'name' => 'EMBA VAC '.uniqid(),
        'code' => 'EMBA-VAC-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    $this->setting = AdmissionSetting::create([
        'batch_id' => $this->batch->id,
        'viva_mcq_threshold' => 25,
    ]);
});

/**
 * Build a full candidate (applicant + profile + result + application),
 * optionally assigned to a viva board.
 */
function vivaCandidate(Batch $batch, string $roll, float $mcq, string $name, ?VivaBoard $board = null): array
{
    $applicant = Applicant::factory()->create(['batch_id' => $batch->id]);

    ApplicantProfile::create([
        'applicant_id' => $applicant->id,
        'batch_id' => $batch->id,
        'full_name' => $name,
        'father_name' => 'Father',
        'mother_name' => 'Mother',
        'date_of_birth' => '1990-01-01',
        'tot_year_of_schooling' => 16,
        'tot_year_of_exp' => 5,
    ]);

    AdmissionResult::create([
        'batch_id' => $batch->id,
        'applicant_id' => $applicant->id,
        'application_number' => $batch->code.'-'.$roll,
        'roll_number' => $roll,
        'mcq_marks' => $mcq,
        'written_marks' => 0,
        'viva_marks' => 0,
        'schooling_marks' => 0,
        'experience_marks' => 0,
        'total_marks' => $mcq,
        'is_adjusted' => false,
        'status' => ResultStatusEnum::FAILED,
    ]);

    $application = Application::create([
        'applicant_id' => $applicant->id,
        'batch_id' => $batch->id,
        'application_number' => $batch->code.'-'.$roll,
        'roll_number' => $roll,
        'status' => ApplicationStatusEnum::COMPLETED,
        'payment_status' => PaymentStatusEnum::PAID,
        'applied_at' => now(),
        'viva_board_id' => $board?->id,
    ]);

    return [$applicant, $application];
}

// ---------------------------------------------------------------------------
// PDF access
// ---------------------------------------------------------------------------

it('lets the owning applicant download their own viva admit card', function () {
    $board = VivaBoard::create(['batch_id' => $this->batch->id, 'board_name' => 'Board A']);
    [$applicant, $application] = vivaCandidate($this->batch, '1000', 30, 'Eligible One', $board);

    $this->actingAs($applicant, 'applicant')
        ->get(route('pdf.viva-admit-card', ['appNo' => $application->application_number, 'action' => 'download']))
        ->assertOk();
});

it('forbids another applicant from accessing someone else viva admit card', function () {
    $board = VivaBoard::create(['batch_id' => $this->batch->id, 'board_name' => 'Board A']);
    [, $application] = vivaCandidate($this->batch, '1000', 30, 'Eligible One', $board);
    $stranger = Applicant::factory()->create(['batch_id' => $this->batch->id]);

    $this->actingAs($stranger, 'applicant')
        ->get(route('pdf.viva-admit-card', $application->application_number))
        ->assertForbidden();
});

it('redirects guests away from the viva admit card', function () {
    $board = VivaBoard::create(['batch_id' => $this->batch->id, 'board_name' => 'Board A']);
    [, $application] = vivaCandidate($this->batch, '1000', 30, 'Eligible One', $board);

    $this->get(route('pdf.viva-admit-card', $application->application_number))
        ->assertRedirect();
});

it('returns 404 when the candidate has no viva board assigned', function () {
    [, $application] = vivaCandidate($this->batch, '1000', 30, 'No Board'); // no board

    $this->actingAs(User::factory()->create())
        ->get(route('pdf.viva-admit-card', $application->application_number))
        ->assertNotFound();
});

it('returns 404 for an unknown application', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('pdf.viva-admit-card', 'NOPE-404'))
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// Admin page
// ---------------------------------------------------------------------------

it('lists viva-eligible candidates with their assigned board', function () {
    $this->actingAs(User::factory()->create());
    CurrentBatch::set($this->batch->id);

    $board = VivaBoard::create(['batch_id' => $this->batch->id, 'board_name' => 'Board Alpha']);
    vivaCandidate($this->batch, '1000', 30, 'Assigned Cand', $board);
    vivaCandidate($this->batch, '1001', 28, 'Unassigned Cand'); // eligible, no board
    vivaCandidate($this->batch, '1002', 20, 'Ineligible Cand'); // below cutoff

    Livewire::test('pages::admin.viva-admit-cards')
        ->assertSee('Assigned Cand')
        ->assertSee('Board Alpha')
        ->assertSee('Unassigned Cand')
        ->assertDontSee('Ineligible Cand');
});

it('will not publish until the viva date is set and every candidate has a board', function () {
    $this->actingAs(User::factory()->create());
    CurrentBatch::set($this->batch->id);

    // Eligible but unassigned, and no viva date set.
    vivaCandidate($this->batch, '1000', 30, 'Unassigned Cand');

    Livewire::test('pages::admin.viva-admit-cards')
        ->call('publishVivaAdmitCards');

    expect($this->setting->fresh()->is_viva_admit_card_published)->toBeFalse();
});

it('publishes viva admit cards once the conditions are met', function () {
    $this->actingAs(User::factory()->create());
    CurrentBatch::set($this->batch->id);

    $this->setting->update(['viva_date' => now()->addDays(10)]);

    $board = VivaBoard::create(['batch_id' => $this->batch->id, 'board_name' => 'Board Alpha']);
    vivaCandidate($this->batch, '1000', 30, 'Assigned Cand', $board);

    Livewire::test('pages::admin.viva-admit-cards')
        ->call('publishVivaAdmitCards')
        ->assertDispatched('toast', variant: 'success')
        ->assertDispatched('close-modal', name: 'publish-viva-admit-cards');

    expect($this->setting->fresh()->is_viva_admit_card_published)->toBeTrue();
});

// ---------------------------------------------------------------------------
// Applicant portal
// ---------------------------------------------------------------------------

it('hides the applicant viva admit card until it is published', function () {
    $board = VivaBoard::create(['batch_id' => $this->batch->id, 'board_name' => 'Board Alpha']);
    [$applicant] = vivaCandidate($this->batch, '1000', 30, 'Eligible One', $board);

    Livewire::actingAs($applicant, 'applicant')
        ->test('pages::applicant.viva-admit-card')
        ->assertSee('not published yet');
});

it('offers the download to a published, eligible, assigned applicant', function () {
    $this->setting->update(['viva_date' => now()->addDays(10), 'viva_admit_card_published_at' => now()]);

    $board = VivaBoard::create(['batch_id' => $this->batch->id, 'board_name' => 'Board Alpha']);
    [$applicant, $application] = vivaCandidate($this->batch, '1000', 30, 'Eligible One', $board);

    Livewire::actingAs($applicant, 'applicant')
        ->test('pages::applicant.viva-admit-card')
        ->assertSee('ready')
        ->assertSee(route('pdf.viva-admit-card', ['appNo' => $application->application_number, 'action' => 'download']), false);
});

it('tells a published but ineligible applicant the card is unavailable', function () {
    $this->setting->update(['viva_admit_card_published_at' => now()]);

    [$applicant] = vivaCandidate($this->batch, '1000', 20, 'Below Cutoff'); // < 25

    Livewire::actingAs($applicant, 'applicant')
        ->test('pages::applicant.viva-admit-card')
        ->assertSee('not available');
});
