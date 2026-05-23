<?php

namespace Database\Seeders;

use App\Enum\BatchStatusEnum;
use App\Models\Batch;
use Illuminate\Database\Seeder;

class BatchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'name' => '45Th EMBA',
                'code' => 'EMBA45',
                'admission_year' => 2024,
                'status' => BatchStatusEnum::CLOSED->value,
            ],
            [
                'name' => '46Th EMBA',
                'code' => 'EMBA46',
                'admission_year' => 2025,
                'status' => BatchStatusEnum::OPEN->value,
            ],
            [
                'name' => '47Th EMBA',
                'code' => 'EMBA47',
                'admission_year' => 2026,
                'status' => BatchStatusEnum::DRAFT->value,
            ],
        ];
        Batch::insert($data);
    }
}
