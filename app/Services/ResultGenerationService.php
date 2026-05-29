<?php

namespace App\Services;

use App\Enums\ResultStatusEnum;
use App\Models\AdmissionResult;
use App\Models\Application;
use App\Models\Batch;
use Illuminate\Support\Facades\DB;

/**
 * Builds the AdmissionResult row that snapshots an applicant's
 * pre-exam, profile-derived marks at the moment payment confirms
 * their application.
 *
 * Two of the five mark components — schooling and experience —
 * are knowable from data the applicant has already submitted by
 * payment time. Persisting them now means:
 *
 *   - Edits to the profile or education history made after payment
 *     do not retroactively change the awarded marks.
 *   - Admins have a single auditable record from which to add the
 *     remaining components (MCQ / written / viva) as exam results
 *     come in.
 *
 * total_marks is initialised to the sum of the two derivable
 * components; mcq_marks / written_marks / viva_marks default to 0
 * and are added to total_marks when entered.
 *
 * The service is idempotent — it keys on (batch_id, applicant_id)
 * via updateOrCreate, so the bKash callback (which is itself replay-
 * safe) can call this without producing duplicate result rows.
 */
class ResultGenerationService
{
    public function __construct(private AdmissionMarkCalculator $calculator) {}

    /**
     * Snapshot the initial AdmissionResult for a freshly-paid application.
     *
     * Loads the relations the calculators need (profile +
     * educationHistories) if not already eager-loaded, computes the two
     * derivable mark components, and persists them.
     */
    public function generateForApplication(Application $application): AdmissionResult
    {
        $application->loadMissing(['applicant.profile', 'applicant.educationHistories']);

        $profile = $application->applicant?->profile;
        $histories = $application->applicant?->educationHistories ?? collect();

        $schoolingMarks = $profile
            ? $this->calculator->schoolingMarks((int) $profile->tot_year_of_schooling, $this->calculator->highestDegree($histories))
            : 0.0;
        $experienceMarks = $profile
            ? $this->calculator->experienceMarks((float) $profile->tot_year_of_exp)
            : 0.0;
        $totalMarks = $schoolingMarks + $experienceMarks;

        return AdmissionResult::updateOrCreate(
            [
                'batch_id' => $application->batch_id,
                'applicant_id' => $application->applicant_id,
            ],
            [
                'application_number' => $application->application_number,
                'roll_number' => $application->roll_number,
                'mcq_marks' => 0,
                'written_marks' => 0,
                'viva_marks' => 0,
                'schooling_marks' => $schoolingMarks,
                'experience_marks' => $experienceMarks,
                'total_marks' => $totalMarks,
                'is_adjusted' => false,
                'status' => ResultStatusEnum::FAILED,
            ],
        );
    }

    /**
     * Rank every AdmissionResult in the batch and persist the rank
     * into `merit_position` (1 = top). Returns the number of rows
     * ranked (PASSED only).
     *
     * Pass/fail is decided from `total_marks` against the batch's
     * configured pass mark (admission_settings.pass_mark, falling back
     * to config('result.passing_marks') when the batch has no settings
     * row) and the structural ceiling config('result.max_marks'):
     *
     *   PASSED  → pass_mark ≤ total_marks ≤ max_marks
     *   FAILED  → anything else (out of range, or below the cutoff)
     *
     * Only PASSED rows receive a `merit_position`; FAILED rows have
     * their `merit_position` cleared to NULL so re-runs after a mark
     * adjustment that drops a candidate below the cutoff do not leave
     * a stale rank.
     *
     * Ordering chain for PASSED rows (all descending):
     *
     *   1. total_marks       — primary score
     *   2. mcq_marks         — first tie-breaker
     *   3. experience_marks  — second tie-breaker
     *   4. written_marks     — third tie-breaker
     *   5. schooling_marks   — fourth tie-breaker
     *   6. viva_marks        — final tie-breaker
     *
     * Candidates still tied after all six columns receive sequential
     * positions (no shared ranks), since merit_position must be
     * unique for downstream selection workflows.
     *
     * Idempotent: re-running on the same batch overwrites existing
     * statuses and positions with the recomputed values, so it is
     * safe to call after any mark adjustment.
     */
    public function generateMeritList(Batch $batch): int
    {
        $batch->loadMissing('admissionSetting');

        $passingMarks = (float) ($batch->admissionSetting?->pass_mark ?? config('result.passing_marks'));
        $maxMarks = (float) config('result.max_marks');

        $results = AdmissionResult::where('batch_id', $batch->id)
            ->orderByDesc('total_marks')
            ->orderByDesc('mcq_marks')
            ->orderByDesc('experience_marks')
            ->orderByDesc('written_marks')
            ->orderByDesc('schooling_marks')
            ->orderByDesc('viva_marks')
            ->get(['id', 'total_marks']);

        if ($results->isEmpty()) {
            return 0;
        }

        $rankedCount = 0;

        DB::transaction(function () use ($results, $passingMarks, $maxMarks, &$rankedCount): void {
            $position = 1;

            foreach ($results as $result) {
                $passed = $result->total_marks >= $passingMarks
                    && $result->total_marks <= $maxMarks;

                AdmissionResult::where('id', $result->id)->update([
                    'status' => $passed ? ResultStatusEnum::PASSED : ResultStatusEnum::FAILED,
                    'merit_position' => $passed ? $position : null,
                ]);

                if ($passed) {
                    $position++;
                    $rankedCount++;
                }
            }
        });

        return $rankedCount;
    }
}
