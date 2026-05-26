<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\ExamCenter;
use Illuminate\Database\Seeder;

class ExamCenterSeeder extends Seeder
{
    /**
     * Seed a fixed set of centers + rooms per batch.
     * 4 centers × 4 rooms × 50 capacity = 800 seats per batch.
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

        $rows = [];
        $now = now();

        foreach (Batch::all() as $batch) {
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
        }

        ExamCenter::insert($rows);
    }
}
