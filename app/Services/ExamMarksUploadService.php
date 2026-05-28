<?php

namespace App\Services;

use App\Models\AdmissionResult;
use App\Models\Batch;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies MCQ and written exam marks to a batch's AdmissionResult rows
 * from an uploaded CSV, recomputing total_marks for every touched row.
 *
 * Rows are matched to results by roll_number. An empty mcq / written
 * cell leaves that component unchanged, so the same format supports an
 * MCQ-first upload (written column left blank — used to decide viva
 * eligibility) followed later by the written marks.
 *
 * The import is all-or-nothing: the entire CSV is validated before any
 * row is written, so a single malformed or unmatched row aborts the
 * whole upload and nothing is persisted.
 */
class ExamMarksUploadService
{
    public const REQUIRED_HEADERS = ['roll_number', 'mcq', 'written'];

    /**
     * Parse and apply the marks CSV.
     *
     * @return array{rows: int, updated: int, unchanged: int}
     *
     * @throws RuntimeException on malformed CSV or unmatched roll numbers.
     */
    public function import(Batch $batch, string $csvPath): array
    {
        $rows = $this->parseCsv($csvPath);

        if ($rows === []) {
            throw new RuntimeException(__('The CSV file contains no data rows.'));
        }

        // Index this batch's results by roll number for matching.
        $results = AdmissionResult::where('batch_id', $batch->id)
            ->get()
            ->keyBy(fn (AdmissionResult $result): string => trim((string) $result->roll_number));

        // Every roll number in the CSV must map to an existing result row.
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

            foreach ($rows as $row) {
                /** @var AdmissionResult $result */
                $result = $results->get($row['roll_number']);

                $changed = false;

                if ($row['mcq'] !== null) {
                    $result->mcq_marks = $row['mcq'];
                    $changed = true;
                }

                if ($row['written'] !== null) {
                    $result->written_marks = $row['written'];
                    $changed = true;
                }

                if (! $changed) {
                    continue;
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
            ];
        });
    }

    /**
     * @return list<array{roll_number: string, mcq: float|null, written: float|null}>
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

        $maxMcq = (float) config('result.max_mcq_marks');
        $maxWritten = (float) config('result.max_written_marks');

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
            $lineNo = 1; // header is line 1

            while (($data = fgetcsv($handle)) !== false) {
                $lineNo++;

                // Skip wholly empty lines.
                if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $roll = trim((string) ($data[$indexMap['roll_number']] ?? ''));
                $mcqRaw = trim((string) ($data[$indexMap['mcq']] ?? ''));
                $writtenRaw = trim((string) ($data[$indexMap['written']] ?? ''));

                if ($roll === '') {
                    throw new RuntimeException(__('Row :n is missing a roll number.', ['n' => $lineNo]));
                }

                $rows[] = [
                    'roll_number' => $roll,
                    'mcq' => $this->parseMark($mcqRaw, __('MCQ'), $maxMcq, $lineNo),
                    'written' => $this->parseMark($writtenRaw, __('written'), $maxWritten, $lineNo),
                ];
            }
        } finally {
            fclose($handle);
        }

        // Catch in-CSV duplicate roll numbers before applying.
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
     * Validate a single mark cell. An empty cell returns null (leave the
     * component unchanged); a present cell must be numeric and within
     * [0, $max].
     */
    private function parseMark(string $raw, string $label, float $max, int $lineNo): ?float
    {
        if ($raw === '') {
            return null;
        }

        if (! is_numeric($raw)) {
            throw new RuntimeException(__(
                'Row :n has a non-numeric :label mark (:val).',
                ['n' => $lineNo, 'label' => $label, 'val' => $raw]
            ));
        }

        $value = (float) $raw;

        if ($value < 0 || $value > $max) {
            throw new RuntimeException(__(
                'Row :n has a :label mark of :val — must be between 0 and :max.',
                ['n' => $lineNo, 'label' => $label, 'val' => $raw, 'max' => $max]
            ));
        }

        return $value;
    }
}
