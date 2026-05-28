<?php

namespace App\Services;

use App\Enums\DegreeType;
use App\Enums\ResultStatusEnum;
use App\Models\AdmissionResult;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\Batch;
use App\Models\EducationHistory;
use Illuminate\Support\Collection;
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

        $schoolingMarks = $this->calculateSchoolingMarks($profile, $histories);
        $experienceMarks = $this->calculateExperienceMarks($profile);
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

    /**
     * Years-to-marks lookup for the academic dimension. Reads the
     * applicant's tot_year_of_schooling (decimal years, truncated to
     * whole years here) and pairs it with the highest degree on file
     * to find a marks value in the fixed matrix:
     *
     *   14 yr Bachelor       →  2
     *   15 yr Bachelor       →  3
     *   16 yr Bachelor       →  4
     *   16 yr B.Sc Eng       →  5
     *   16 yr Master         →  4
     *   17 yr Master         →  5
     *   17 yr M.Sc Eng       →  5  (same row as 17yr Master)
     *
     * Combinations outside the matrix award 0. The final value is
     * capped at config('result.max_schooling_marks') as a defensive
     * upper bound.
     *
     * Engineering vs non-engineering is detected by looking at the
     * highest degree's `major` / `name` text — the schema does not
     * distinguish engineering at the enum level.
     *
     * @param  Collection<int, EducationHistory>  $histories
     */
    private function calculateSchoolingMarks(?ApplicantProfile $profile, Collection $histories): float
    {
        if (! $profile) {
            return 0.0;
        }

        $years = (int) $profile->tot_year_of_schooling;
        $highest = $this->highestDegree($histories);

        if (! $highest) {
            return 0.0;
        }

        $type = $highest->type;
        $isEngineering = $this->isEngineeringDegree($highest);

        $marks = match (true) {
            $years === 14 && $type === DegreeType::UNDERGRADUATE => 2,
            $years === 15 && $type === DegreeType::UNDERGRADUATE => 3,
            $years === 16 && $type === DegreeType::UNDERGRADUATE && $isEngineering => 5,
            $years === 16 && $type === DegreeType::UNDERGRADUATE => 4,
            $years === 16 && $type === DegreeType::GRADUATE => 4,
            $years === 17 && $type === DegreeType::GRADUATE => 5,
            default => 0,
        };

        return (float) min($marks, (int) config('result.max_schooling_marks'));
    }

    /**
     * Years-to-marks lookup for the experience dimension.
     *
     * Rule: the first two years of experience count as zero, then
     * every additional whole year adds one point. The maximum is
     * config('result.max_experience_marks') (10 by default), reached
     * at 12 years (2 ignored + 10 counted). Any experience beyond
     * 12 years adds no further points.
     *
     * Partial years are truncated downward — a 2.9-year applicant
     * gets 0, a 12.9-year applicant gets 10.
     */
    private function calculateExperienceMarks(?ApplicantProfile $profile): float
    {
        if (! $profile) {
            return 0.0;
        }

        $years = (int) floor((float) $profile->tot_year_of_exp);
        $effective = max(0, $years - 2);
        $cap = (int) config('result.max_experience_marks');

        return (float) min($effective, $cap);
    }

    /**
     * Pick the highest-level degree on file. Graduate (Master)
     * outranks Undergraduate (Bachelor); SSC / HSC / Other are
     * ignored because the schooling matrix only awards points for
     * Bachelor and Master entries.
     *
     * @param  Collection<int, EducationHistory>  $histories
     */
    private function highestDegree(Collection $histories): ?EducationHistory
    {
        return $histories->firstWhere('type', DegreeType::GRADUATE)
            ?? $histories->firstWhere('type', DegreeType::UNDERGRADUATE);
    }

    /**
     * Engineering check searches the degree's `major` and `name`
     * strings for the word "engineering" (case-insensitive). Used
     * to award the +1 bonus at the 16-year Bachelor row.
     */
    private function isEngineeringDegree(EducationHistory $degree): bool
    {
        $haystack = strtolower(($degree->major ?? '').' '.($degree->name ?? ''));

        return str_contains($haystack, 'engineering');
    }
}
