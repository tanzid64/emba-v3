<?php

namespace App\Services;

use App\Models\AdmissionResult;
use App\Models\Batch;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies viva results to a batch's AdmissionResult rows from an uploaded
 * CSV: the viva mark, plus the re-verified years of schooling and
 * experience the viva board confirmed against the candidate's documents.
 *
 * The years are converted to marks through the shared
 * AdmissionMarkCalculator (using the candidate's highest degree on file
 * for the schooling matrix). When a recomputed schooling or experience
 * mark differs from the value currently stored on the result, the
 * uploaded figure wins and the row is flagged is_adjusted = true. A
 * changed viva mark alone does not flag the row.
 *
 * Rows are matched by roll_number. A blank cell leaves that component
 * untouched. The import is all-or-nothing: the whole CSV is validated
 * before any row is written.
 */
class VivaMarksUploadService
{
    public const REQUIRED_HEADERS = ['roll_number', 'viva', 'schooling_years', 'experience_years'];

    private const MAX_YEARS = 60.0;

    public function __construct(private AdmissionMarkCalculator $calculator) {}

    /**
     * Parse and apply the viva CSV.
     *
     * @return array{rows: int, updated: int, unchanged: int, adjusted: int}
     *
     * @throws RuntimeException on malformed CSV or unmatched roll numbers.
     */
    public function import(Batch $batch, string $csvPath): array
    {
        $rows = $this->parseCsv($csvPath);

        if ($rows === []) {
            throw new RuntimeException(__('The CSV file contains no data rows.'));
        }

        $results = AdmissionResult::where('batch_id', $batch->id)
            ->with(['applicant.profile', 'applicant.educationHistories'])
            ->get()
            ->keyBy(fn (AdmissionResult $result): string => trim((string) $result->roll_number));

        $unknown = [];
        foreach ($rows as $row) {
            if (! $results->has($row['roll_number'])) {
                $unknown[] = $row['roll_number'];
            }
        }

        if ($unknown !== []) {
            $shown = array_slice($unknown, 0, 20);
            throw new RuntimeException(__(
                'These roll number(s) have no result row in this batch: :rolls',
                ['rolls' => implode(', ', $shown).(count($unknown) > 20 ? '…' : '')]
            ));
        }

        return DB::transaction(function () use ($rows, $results): array {
            $updated = 0;
            $adjusted = 0;

            foreach ($rows as $row) {
                /** @var AdmissionResult $result */
                $result = $results->get($row['roll_number']);

                $changed = false;
                $rowAdjusted = false;

                if ($row['viva'] !== null) {
                    $result->viva_marks = $row['viva'];
                    $changed = true;
                }

                if ($row['schooling_years'] !== null) {
                    $histories = $result->applicant?->educationHistories ?? collect();
                    $newSchooling = $this->calculator->schoolingMarks(
                        (int) $row['schooling_years'],
                        $this->calculator->highestDegree($histories),
                    );

                    if (round($newSchooling, 2) !== round((float) $result->schooling_marks, 2)) {
                        $result->schooling_marks = $newSchooling;
                        $changed = true;
                        $rowAdjusted = true;
                    }
                }

                if ($row['experience_years'] !== null) {
                    $newExperience = $this->calculator->experienceMarks((float) $row['experience_years']);

                    if (round($newExperience, 2) !== round((float) $result->experience_marks, 2)) {
                        $result->experience_marks = $newExperience;
                        $changed = true;
                        $rowAdjusted = true;
                    }
                }

                if (! $changed) {
                    continue;
                }

                if ($rowAdjusted) {
                    $result->is_adjusted = true;
                    $adjusted++;
                }

                $result->total_marks = (float) $result->schooling_marks
                    + (float) $result->experience_marks
                    + (float) $result->viva_marks
                    + (float) $result->mcq_marks
                    + (float) $result->written_marks;

                $result->save();
                $updated++;
            }

            return [
                'rows' => count($rows),
                'updated' => $updated,
                'unchanged' => count($rows) - $updated,
                'adjusted' => $adjusted,
            ];
        });
    }

    /**
     * @return list<array{roll_number: string, viva: float|null, schooling_years: float|null, experience_years: float|null}>
     */
    public function parseCsv(string $csvPath): array
    {
        if (! is_readable($csvPath)) {
            throw new RuntimeException(__('Unable to read the uploaded CSV file.'));
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new RuntimeException(__('Unable to open the uploaded CSV file.'));
        }

        $maxViva = (float) config('result.max_viva_marks');

        try {
            $headers = fgetcsv($handle);
            if ($headers === false) {
                throw new RuntimeException(__('The CSV file is empty.'));
            }

            $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $headers);

            $missing = array_diff(self::REQUIRED_HEADERS, $headers);
            if ($missing !== []) {
                throw new RuntimeException(__(
                    'Missing required CSV column(s): :cols',
                    ['cols' => implode(', ', $missing)]
                ));
            }

            $indexMap = array_flip($headers);
            $rows = [];
            $lineNo = 1;

            while (($data = fgetcsv($handle)) !== false) {
                $lineNo++;

                if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $roll = trim((string) ($data[$indexMap['roll_number']] ?? ''));

                if ($roll === '') {
                    throw new RuntimeException(__('Row :n is missing a roll number.', ['n' => $lineNo]));
                }

                $rows[] = [
                    'roll_number' => $roll,
                    'viva' => $this->parseNumber(trim((string) ($data[$indexMap['viva']] ?? '')), __('viva'), $maxViva, $lineNo),
                    'schooling_years' => $this->parseNumber(trim((string) ($data[$indexMap['schooling_years']] ?? '')), __('schooling years'), self::MAX_YEARS, $lineNo),
                    'experience_years' => $this->parseNumber(trim((string) ($data[$indexMap['experience_years']] ?? '')), __('experience years'), self::MAX_YEARS, $lineNo),
                ];
            }
        } finally {
            fclose($handle);
        }

        $seen = [];
        foreach ($rows as $i => $row) {
            if (isset($seen[$row['roll_number']])) {
                throw new RuntimeException(__(
                    'Duplicate roll number ":roll" at row :n.',
                    ['roll' => $row['roll_number'], 'n' => $i + 2]
                ));
            }
            $seen[$row['roll_number']] = true;
        }

        return $rows;
    }

    /**
     * Validate a single numeric cell. An empty cell returns null (leave
     * that component unchanged); a present cell must be numeric and within
     * [0, $max].
     */
    private function parseNumber(string $raw, string $label, float $max, int $lineNo): ?float
    {
        if ($raw === '') {
            return null;
        }

        if (! is_numeric($raw)) {
            throw new RuntimeException(__(
                'Row :n has a non-numeric :label value (:val).',
                ['n' => $lineNo, 'label' => $label, 'val' => $raw]
            ));
        }

        $value = (float) $raw;

        if ($value < 0 || $value > $max) {
            throw new RuntimeException(__(
                'Row :n has a :label value of :val — must be between 0 and :max.',
                ['n' => $lineNo, 'label' => $label, 'val' => $raw, 'max' => $max]
            ));
        }

        return $value;
    }
}
