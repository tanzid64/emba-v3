<?php

namespace Database\Seeders;

use App\Enum\BatchStatusEnum;
use App\Models\Batch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApplicantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $batchId = Batch::where('status', BatchStatusEnum::OPEN)->first()->id;
        $data = [
            'batch_id' => $batchId,
            'email' => 'tanzid3@gmail.com',
            'phone_number' => '01708915045',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        // Insert the applicant data into the database
        DB::table('applicants')->insert($data);
    }
}
