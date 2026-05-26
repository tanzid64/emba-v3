<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(UserSeeder::class);
        $this->call(BatchSeeder::class);
        $this->call(ApplicantSeeder::class);
        $this->call(DistrictSeeder::class);
        $this->call(UpazilaSeeder::class);
        $this->call(ApplicantProfileSeeder::class);
        $this->call(AddressSeeder::class);
        $this->call(EducationHistorySeeder::class);
        $this->call(ExpHistorySeeder::class);
        $this->call(ApplicationSeeder::class);
        $this->call(ExamCenterSeeder::class);
    }
}
