<?php

namespace App\Services;

use App\Enums\DegreeType;
use App\Models\EducationHistory;
use Illuminate\Support\Collection;

/**
 * Single source of truth for the profile-derived mark components
 * (schooling and experience), keyed off years of schooling / experience.
 *
 * Used both when snapshotting an AdmissionResult at payment time
 * (ResultGenerationService) and when the viva board re-verifies a
 * candidate's years and re-derives the marks (VivaMarksUploadService).
 */
class AdmissionMarkCalculator
{
    /**
     * Years-to-marks lookup for the academic dimension, paired with the
     * candidate's highest degree:
     *
     *   14 yr Bachelor       →  2
     *   15 yr Bachelor       →  3
     *   16 yr Bachelor       →  4
     *   16 yr B.Sc Eng       →  5
     *   16 yr Master         →  4
     *   17 yr Master         →  5
     *
     * Combinations outside the matrix award 0, capped at
     * config('result.max_schooling_marks').
     */
    public function schoolingMarks(int $years, ?EducationHistory $highestDegree): float
    {
        if (! $highestDegree) {
            return 0.0;
        }

        $type = $highestDegree->type;
        $isEngineering = $this->isEngineeringDegree($highestDegree);

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
     * Years-to-marks lookup for the experience dimension: the first two
     * years count as zero, then every additional whole year adds one
     * point, capped at config('result.max_experience_marks'). Partial
     * years are truncated downward.
     */
    public function experienceMarks(float $years): float
    {
        $effective = max(0, (int) floor($years) - 2);
        $cap = (int) config('result.max_experience_marks');

        return (float) min($effective, $cap);
    }

    /**
     * Pick the highest-level degree on file. Graduate (Master) outranks
     * Undergraduate (Bachelor); SSC / HSC / Other are ignored because the
     * schooling matrix only awards points for Bachelor and Master entries.
     *
     * @param  Collection<int, EducationHistory>  $histories
     */
    public function highestDegree(Collection $histories): ?EducationHistory
    {
        return $histories->firstWhere('type', DegreeType::GRADUATE)
            ?? $histories->firstWhere('type', DegreeType::UNDERGRADUATE);
    }

    /**
     * Engineering check searches the degree's `major` and `name` strings
     * for the word "engineering" (case-insensitive) — used to award the
     * +1 bonus at the 16-year Bachelor row.
     */
    public function isEngineeringDegree(EducationHistory $degree): bool
    {
        $haystack = strtolower(($degree->major ?? '').' '.($degree->name ?? ''));

        return str_contains($haystack, 'engineering');
    }
}
