<?php

namespace Database\Seeders;

use App\Enums\PaymentStatusEnum;
use App\Models\Application;
use App\Models\Batch;
use App\Models\ExamCenter;
use Illuminate\Database\Seeder;

class ExamCenterSeeder extends Seeder
{
    /**
     * Seed a fixed set of centers + rooms per batch and assign every
     * confirmed applicant to a room in roll-number order — mirrors the
     * production `ExamCenterUploadService::assignSeats` rule so the seeded
     * admit-card flow has realistic data.
     *
     * 4 centers × 4 rooms × 50 capacity = 800 seats per batch (covers the
     * 750 paid applicants ApplicationSeeder creates per batch).
     */
    public function run(): void
    {
        $centers = [
            ['no' => 'C-01', 'name' => 'Main Campus — Building A'],
            ['no' => 'C-02', 'name' => 'Main Campus — Building B'],
            ['no' => 'C-03', 'name' => 'Annex — South Wing'],
            ['no' => 'C-04', 'name' => 'Annex — North Wing'],
        ];

        $rooms = ['Room 101', 'Room 102', 'Room 201', 'Room 202'];
        $now = now();

        foreach (Batch::all() as $batch) {
            $rows = [];
            foreach ($centers as $center) {
                foreach ($rooms as $room) {
                    $rows[] = [
                        'batch_id' => $batch->id,
                        'center_no' => $center['no'],
                        'center_name' => $center['name'],
                        'room_name' => $room,
                        'capacity' => 50,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            ExamCenter::insert($rows);

            $this->assignConfirmedApplicants($batch);

            // Mark the system-managed milestone so quick-settings + exam-centers
            // gating treat seeded batches as fully uploaded.
            $batch->admissionSetting()->update(['exam_center_uploaded_at' => now()]);
        }
    }

    /**
     * Roll-ordered room assignment for the batch's confirmed applicants,
     * filling one room before moving to the next.
     */
    private function assignConfirmedApplicants(Batch $batch): void
    {
        $rooms = ExamCenter::where('batch_id', $batch->id)
            ->orderBy('id')
            ->get(['id', 'capacity']);

        $confirmedIds = Application::where('batch_id', $batch->id)
            ->whereIn('payment_status', [PaymentStatusEnum::PAID->value, PaymentStatusEnum::COMPLETED->value])
            ->whereNotNull('roll_number')
            ->get(['id', 'roll_number'])
            ->sortBy(fn (Application $a) => (int) $a->roll_number)
            ->pluck('id')
            ->values();

        $cursor = 0;
        $total = $confirmedIds->count();

        foreach ($rooms as $room) {
            if ($cursor >= $total) {
                break;
            }

            $slice = $confirmedIds->slice($cursor, $room->capacity)->all();
            if ($slice === []) {
                break;
            }

            Application::whereIn('id', $slice)->update(['exam_center_id' => $room->id]);
            $cursor += count($slice);
        }
    }
}
