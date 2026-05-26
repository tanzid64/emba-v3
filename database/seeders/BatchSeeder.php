<?php

namespace Database\Seeders;

use App\Enum\BatchStatusEnum;
use App\Models\AdmissionSetting;
use App\Models\Batch;
use Illuminate\Database\Seeder;

class BatchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $batches = [
            [
                'batch' => [
                    'name' => '45Th EMBA',
                    'code' => 'EMBA45',
                    'admission_year' => 2024,
                    'status' => BatchStatusEnum::CLOSED->value,
                ],
                'settings' => [
                    'intake_started_at' => '2024-01-01',
                    'intake_ended_at' => '2024-02-15',
                    'application_payment_ended_at' => '2024-02-20',
                    'admit_card_published_at' => '2024-02-25',
                    'exam_date' => '2024-03-05 10:00:00',
                    'viva_date' => '2024-03-20 10:00:00',
                    'result_published_at' => '2024-04-05 10:00:00',
                ],
            ],
            [
                'batch' => [
                    'name' => '46Th EMBA',
                    'code' => 'EMBA46',
                    'admission_year' => 2025,
                    'status' => BatchStatusEnum::OPEN->value,
                ],
                'settings' => [
                    'intake_started_at' => '2025-01-15',
                    'intake_ended_at' => '2025-03-15',
                    'application_payment_ended_at' => '2025-03-20',
                    'admit_card_published_at' => null,
                    'exam_date' => '2025-04-05 10:00:00',
                    'viva_date' => '2025-04-20 10:00:00',
                    'result_published_at' => null,
                ],
            ],
            [
                'batch' => [
                    'name' => '47Th EMBA',
                    'code' => 'EMBA47',
                    'admission_year' => 2026,
                    'status' => BatchStatusEnum::DRAFT->value,
                ],
                'settings' => [
                    'intake_started_at' => '2026-06-01',
                    'intake_ended_at' => '2026-07-31',
                    'application_payment_ended_at' => '2026-08-05',
                    'admit_card_published_at' => null,
                    'exam_date' => '2026-08-20 10:00:00',
                    'viva_date' => '2026-09-05 10:00:00',
                    'result_published_at' => null,
                ],
            ],
        ];

        foreach ($batches as $row) {
            $batch = Batch::create($row['batch']);

            AdmissionSetting::create([
                'batch_id' => $batch->id,
                'application_fee' => 2500,
                'enrollment_fee' => 500,
                'admission_fee' => 12000,
                'application_number_start_from' => 1000,
                'roll_number_start_from' => 1000,
                ...$row['settings'],
            ]);
        }
    }
}
