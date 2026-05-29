<?php

use App\Enum\BatchStatusEnum;
use App\Enums\ApplicationStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\ResultStatusEnum;
use App\Models\AdmissionResult;
use App\Models\AdmissionSetting;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Batch;
use App\Models\User;
use App\Models\VivaBoard;
use App\Services\VivaBoardAssignmentService;
use App\Support\CurrentBatch;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->batch = Batch::create([
        'name' => 'EMBA VBA '.uniqid(),
        'code' => 'EMBA-VBA-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);

    AdmissionSetting::create(['batch_id' => $this->batch->id, 'viva_mcq_threshold' => 25]);

    CurrentBatch::set($this->batch->id);

    $this->service = new VivaBoardAssignmentService;
});

/**
 * Create a candidate with both the AdmissionResult (which decides
 * viva eligibility) and the Application (which carries viva_board_id).
 */
function makeCandidate(Batch $batch, string $roll, float $mcq, ?int $vivaBoardId = null): Application
{
    $applicant = Applicant::factory()->create(['batch_id' => $batch->id]);

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

    return Application::create([
        'applicant_id' => $applicant->id,
        'batch_id' => $batch->id,
        'application_number' => $batch->code.'-'.$roll,
        'roll_number' => $roll,
        'status' => ApplicationStatusEnum::COMPLETED,
        'payment_status' => PaymentStatusEnum::PAID,
        'applied_at' => now(),
        'viva_board_id' => $vivaBoardId,
    ]);
}

/**
 * @return array<int, VivaBoard>
 */
function makeBoards(Batch $batch, array $names): array
{
    return array_map(
        fn (string $name) => VivaBoard::create(['batch_id' => $batch->id, 'board_name' => $name]),
        $names,
    );
}

it('deals eligible candidates evenly across the boards', function () {
    makeBoards($this->batch, ['Board A', 'Board B', 'Board C']);

    foreach (range(1, 7) as $i) {
        makeCandidate($this->batch, '100'.$i, 30);
    }

    $assigned = $this->service->assignUnassigned($this->batch);

    expect($assigned)->toBe(7);

    $counts = VivaBoard::where('batch_id', $this->batch->id)
        ->withCount('applications')
        ->pluck('applications_count');

    // 7 across 3 boards → balanced 3/2/2.
    expect($counts->sum())->toBe(7);
    expect($counts->max() - $counts->min())->toBeLessThanOrEqual(1);
});

it('skips candidates below the MCQ threshold', function () {
    [$board] = makeBoards($this->batch, ['Board A']);

    $eligible = makeCandidate($this->batch, '1000', 30);    // ≥ 25 → assigned
    $ineligible = makeCandidate($this->batch, '1001', 20);  // < 25 → left alone

    $assigned = $this->service->assignUnassigned($this->batch);

    expect($assigned)->toBe(1);
    expect($eligible->fresh()->viva_board_id)->toBe($board->id);
    expect($ineligible->fresh()->viva_board_id)->toBeNull();
});

it('preserves existing assignments and only deals out the unassigned', function () {
    [$boardA, $boardB] = makeBoards($this->batch, ['Board A', 'Board B']);

    // Two already pinned to Board A.
    $pinned1 = makeCandidate($this->batch, '1000', 30, $boardA->id);
    $pinned2 = makeCandidate($this->batch, '1001', 30, $boardA->id);

    // Two fresh eligible candidates with no board yet.
    makeCandidate($this->batch, '1002', 30);
    makeCandidate($this->batch, '1003', 30);

    $assigned = $this->service->assignUnassigned($this->batch);

    expect($assigned)->toBe(2);

    // Pinned ones never move.
    expect($pinned1->fresh()->viva_board_id)->toBe($boardA->id);
    expect($pinned2->fresh()->viva_board_id)->toBe($boardA->id);

    // The two new ones balance the boards: A already had 2, so both go to B.
    expect(Application::where('viva_board_id', $boardA->id)->count())->toBe(2);
    expect(Application::where('viva_board_id', $boardB->id)->count())->toBe(2);
});

it('is a no-op on a second run', function () {
    makeBoards($this->batch, ['Board A', 'Board B']);

    foreach (range(1, 4) as $i) {
        makeCandidate($this->batch, '100'.$i, 30);
    }

    expect($this->service->assignUnassigned($this->batch))->toBe(4);
    expect($this->service->assignUnassigned($this->batch))->toBe(0);
});

it('assigns nothing when the batch has no boards', function () {
    $candidate = makeCandidate($this->batch, '1000', 30);

    $assigned = $this->service->assignUnassigned($this->batch);

    expect($assigned)->toBe(0);
    expect($candidate->fresh()->viva_board_id)->toBeNull();
});

it('falls back to the config threshold when the batch has no admission setting', function () {
    $batch = Batch::create([
        'name' => 'EMBA NoSetting '.uniqid(),
        'code' => 'EMBA-NS-'.strtoupper(substr(uniqid(), -4)),
        'admission_year' => 2026,
        'status' => BatchStatusEnum::OPEN,
    ]);
    [$board] = makeBoards($batch, ['Board A']);

    $threshold = (float) config('result.viva_mcq_threshold');
    $eligible = makeCandidate($batch, '2000', $threshold);       // == cutoff → in
    $ineligible = makeCandidate($batch, '2001', $threshold - 1); // below → out

    expect($this->service->assignUnassigned($batch))->toBe(1);
    expect($eligible->fresh()->viva_board_id)->toBe($board->id);
    expect($ineligible->fresh()->viva_board_id)->toBeNull();
});

it('previews how many candidates a run would assign', function () {
    [$boardA] = makeBoards($this->batch, ['Board A', 'Board B']);

    makeCandidate($this->batch, '1000', 30, $boardA->id); // eligible, already on a board
    makeCandidate($this->batch, '1001', 30);              // eligible, unassigned
    makeCandidate($this->batch, '1002', 30);              // eligible, unassigned
    makeCandidate($this->batch, '1003', 20);              // below cutoff

    $preview = $this->service->preview($this->batch);

    expect($preview)->toBe(['boards' => 2, 'eligible' => 3, 'unassigned' => 2]);
});

it('confirms the assignment from the modal, then closes it with a success toast', function () {
    makeBoards($this->batch, ['Board A', 'Board B']);

    foreach (range(1, 3) as $i) {
        makeCandidate($this->batch, '100'.$i, 30);
    }

    Livewire::test('pages::admin.viva-boards')
        ->call('assignBoards')
        ->assertDispatched('toast', variant: 'success')
        ->assertDispatched('close-modal', name: 'viva-board-assign');

    expect(Application::where('batch_id', $this->batch->id)->whereNotNull('viva_board_id')->count())->toBe(3);
});

it('warns from the page when there are no boards yet', function () {
    $candidate = makeCandidate($this->batch, '1000', 30);

    Livewire::test('pages::admin.viva-boards')
        ->call('assignBoards')
        ->assertDispatched('toast', variant: 'warning');

    expect($candidate->fresh()->viva_board_id)->toBeNull();
});

it('reports nothing to do from the page when everyone already has a board', function () {
    [$board] = makeBoards($this->batch, ['Board A']);
    makeCandidate($this->batch, '1000', 30, $board->id);

    Livewire::test('pages::admin.viva-boards')
        ->call('assignBoards')
        ->assertDispatched('toast', variant: 'info');
});
