<?php

namespace App\Services;

use App\Models\AdmissionResult;
use App\Models\Application;
use App\Models\Batch;
use App\Models\VivaBoard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Distributes the batch's viva-eligible candidates across its viva
 * boards, dealing each one to the currently least-full board so the
 * boards end up balanced.
 *
 * Eligibility mirrors the Viva Shortlist page: a candidate is eligible
 * when their AdmissionResult.mcq_marks meets the per-batch
 * admission_settings.viva_mcq_threshold (falling back to
 * config('result.viva_mcq_threshold')). The board itself lives on
 * Application.viva_board_id, linked back to the result through
 * (batch_id, applicant_id).
 *
 * The pass is additive and idempotent: already-assigned applications
 * are left untouched and only seed each board's running count, so
 * re-running only places candidates who still have no board. Calling
 * it again with nothing new to place is a no-op.
 */
class VivaBoardAssignmentService
{
    /**
     * Assign every eligible-but-unassigned application to a board,
     * balancing against any assignments that already exist.
     *
     * Returns the number of applications newly assigned.
     */
    public function assignUnassigned(Batch $batch): int
    {
        $boards = VivaBoard::where('batch_id', $batch->id)
            ->orderBy('board_name')
            ->orderBy('id')
            ->pluck('id');

        if ($boards->isEmpty()) {
            return 0;
        }

        $eligibleApplicantIds = $this->eligibleApplicantIds($batch);

        if ($eligibleApplicantIds->isEmpty()) {
            return 0;
        }

        $unassigned = Application::where('batch_id', $batch->id)
            ->whereIn('applicant_id', $eligibleApplicantIds)
            ->whereNull('viva_board_id')
            ->pluck('id');

        if ($unassigned->isEmpty()) {
            return 0;
        }

        // Seed each board's running count from its current load so new
        // candidates flow into the emptiest boards first.
        $counts = $boards->mapWithKeys(fn (int $boardId) => [$boardId => 0])->all();

        Application::where('batch_id', $batch->id)
            ->whereNotNull('viva_board_id')
            ->selectRaw('viva_board_id, COUNT(*) as total')
            ->groupBy('viva_board_id')
            ->pluck('total', 'viva_board_id')
            ->each(function (int $total, int $boardId) use (&$counts): void {
                if (array_key_exists($boardId, $counts)) {
                    $counts[$boardId] = $total;
                }
            });

        // Deal each unassigned application to the least-full board,
        // bucketing the ids so we can flush one update per board.
        $buckets = [];

        foreach ($unassigned as $applicationId) {
            $targetBoardId = $this->leastFullBoard($counts);
            $buckets[$targetBoardId][] = $applicationId;
            $counts[$targetBoardId]++;
        }

        DB::transaction(function () use ($buckets): void {
            foreach ($buckets as $boardId => $applicationIds) {
                Application::whereIn('id', $applicationIds)
                    ->update(['viva_board_id' => $boardId]);
            }
        });

        return $unassigned->count();
    }

    /**
     * Read-only summary of what an assignment run would do right now:
     * how many boards exist, how many candidates are viva-eligible, and
     * how many of those still have no board (i.e. would be assigned).
     *
     * @return array{boards: int, eligible: int, unassigned: int}
     */
    public function preview(Batch $batch): array
    {
        $eligibleApplicantIds = $this->eligibleApplicantIds($batch);

        $unassigned = $eligibleApplicantIds->isEmpty()
            ? 0
            : Application::where('batch_id', $batch->id)
                ->whereIn('applicant_id', $eligibleApplicantIds)
                ->whereNull('viva_board_id')
                ->count();

        return [
            'boards' => VivaBoard::where('batch_id', $batch->id)->count(),
            'eligible' => $eligibleApplicantIds->count(),
            'unassigned' => $unassigned,
        ];
    }

    /**
     * Applicant ids in the batch whose MCQ marks clear the viva cutoff.
     *
     * @return Collection<int, int>
     */
    private function eligibleApplicantIds(Batch $batch): Collection
    {
        return AdmissionResult::where('batch_id', $batch->id)
            ->where('mcq_marks', '>=', $this->threshold($batch))
            ->pluck('applicant_id');
    }

    /**
     * MCQ mark at/above which a candidate is eligible to sit for the
     * viva — the per-batch setting, falling back to the config default.
     */
    private function threshold(Batch $batch): float
    {
        $batch->loadMissing('admissionSetting');

        return (float) ($batch->admissionSetting?->viva_mcq_threshold
            ?? config('result.viva_mcq_threshold'));
    }

    /**
     * Id of the board with the lowest running count. Ties resolve to
     * the first board in iteration order (boards are ordered by name),
     * keeping the spread stable and predictable.
     *
     * @param  array<int, int>  $counts  board id => running count
     */
    private function leastFullBoard(array $counts): int
    {
        $targetBoardId = array_key_first($counts);
        $lowest = $counts[$targetBoardId];

        foreach ($counts as $boardId => $count) {
            if ($count < $lowest) {
                $lowest = $count;
                $targetBoardId = $boardId;
            }
        }

        return $targetBoardId;
    }
}
