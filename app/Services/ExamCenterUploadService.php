<?php

namespace App\Services;

use App\Enums\PaymentStatusEnum;
use App\Models\Application;
use App\Models\Batch;
use App\Models\ExamCenter;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExamCenterUploadService
{
    public const REQUIRED_HEADERS = ['center_no', 'center_name', 'room_name', 'capacity'];

    /**
     * Parse and persist exam centers from a CSV, then assign every confirmed
     * applicant to a room in roll-number order.
     *
     * Replaces all existing centers and assignments for the batch.
     *
     * @return array{centers: int, rooms: int, assigned: int}
     *
     * @throws RuntimeException on malformed CSV or insufficient total capacity.
     */
    public function import(Batch $batch, string $csvPath): array
    {
        $rows = $this->parseCsv($csvPath);

        if ($rows === []) {
            throw new RuntimeException(__('The CSV file contains no data rows.'));
        }

        $confirmedCount = $this->confirmedCount($batch);
        $totalCapacity = array_sum(array_column($rows, 'capacity'));

        if ($totalCapacity < $confirmedCount) {
            throw new RuntimeException(__(
                'Total capacity (:cap) is less than confirmed applicants (:c). Add more rooms or increase capacity.',
                ['cap' => $totalCapacity, 'c' => $confirmedCount]
            ));
        }

        return DB::transaction(function () use ($batch, $rows) {
            // Clear previous assignments + centers for this batch.
            Application::where('batch_id', $batch->id)
                ->update(['exam_center_id' => null]);
            ExamCenter::where('batch_id', $batch->id)->delete();

            // Insert centers in CSV order; track id + capacity to drive seating.
            $now = now();
            $slots = [];
            foreach ($rows as $row) {
                $id = ExamCenter::insertGetId([
                    'batch_id' => $batch->id,
                    'center_no' => $row['center_no'],
                    'center_name' => $row['center_name'],
                    'room_name' => $row['room_name'],
                    'capacity' => $row['capacity'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $slots[] = ['id' => $id, 'capacity' => $row['capacity']];
            }

            $assigned = $this->assignSeats($batch, $slots);

            // System-managed milestone — surfaced read-only in the quick-settings
            // page and gates the attendance-sheet / seat-label PDFs.
            $batch->admissionSetting()->update(['exam_center_uploaded_at' => now()]);

            return [
                'centers' => collect($rows)->pluck('center_no')->unique()->count(),
                'rooms' => count($rows),
                'assigned' => $assigned,
            ];
        });
    }

    /**
     * @return list<array{center_no: string, center_name: string, room_name: string, capacity: int}>
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

                $centerNo = trim((string) ($data[$indexMap['center_no']] ?? ''));
                $centerName = trim((string) ($data[$indexMap['center_name']] ?? ''));
                $roomName = trim((string) ($data[$indexMap['room_name']] ?? ''));
                $capacityRaw = trim((string) ($data[$indexMap['capacity']] ?? ''));

                if ($centerNo === '' || $centerName === '' || $roomName === '' || $capacityRaw === '') {
                    throw new RuntimeException(__('Row :n has empty required fields.', ['n' => $lineNo]));
                }

                if (! ctype_digit($capacityRaw) || (int) $capacityRaw <= 0) {
                    throw new RuntimeException(__(
                        'Row :n has invalid capacity (:val). Must be a positive integer.',
                        ['n' => $lineNo, 'val' => $capacityRaw]
                    ));
                }

                $rows[] = [
                    'center_no' => $centerNo,
                    'center_name' => $centerName,
                    'room_name' => $roomName,
                    'capacity' => (int) $capacityRaw,
                ];
            }
        } finally {
            fclose($handle);
        }

        // Catch in-CSV duplicates of (center_no, room_name) before hitting the DB unique index.
        $seen = [];
        foreach ($rows as $i => $row) {
            $key = $row['center_no'].'|'.$row['room_name'];
            if (isset($seen[$key])) {
                throw new RuntimeException(__(
                    'Duplicate room ":room" for center ":center" at row :n.',
                    ['room' => $row['room_name'], 'center' => $row['center_no'], 'n' => $i + 2]
                ));
            }
            $seen[$key] = true;
        }

        return $rows;
    }

    public function confirmedCount(Batch $batch): int
    {
        return Application::where('batch_id', $batch->id)
            ->whereIn('payment_status', [PaymentStatusEnum::PAID->value, PaymentStatusEnum::COMPLETED->value])
            ->count();
    }

    /**
     * Assign confirmed applicants to rooms: roll-number-ordered, filling
     * one room before moving to the next.
     *
     * @param  list<array{id: int, capacity: int}>  $slots
     */
    private function assignSeats(Batch $batch, array $slots): int
    {
        // Sort by numeric roll_number in PHP — portable across SQLite (tests) and MySQL (prod).
        $confirmedIds = Application::where('batch_id', $batch->id)
            ->whereIn('payment_status', [PaymentStatusEnum::PAID->value, PaymentStatusEnum::COMPLETED->value])
            ->whereNotNull('roll_number')
            ->get(['id', 'roll_number'])
            ->sortBy(fn (Application $a) => (int) $a->roll_number)
            ->pluck('id')
            ->values();

        $assigned = 0;
        $cursor = 0;
        $total = $confirmedIds->count();

        foreach ($slots as $slot) {
            if ($cursor >= $total) {
                break;
            }

            for ($filled = 0; $filled < $slot['capacity'] && $cursor < $total; $filled++) {
                Application::where('id', $confirmedIds[$cursor])->update([
                    'exam_center_id' => $slot['id'],
                ]);
                $cursor++;
                $assigned++;
            }
        }

        return $assigned;
    }
}
