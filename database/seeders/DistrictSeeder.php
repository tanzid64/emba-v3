<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DistrictSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('districts')->truncate();
        Schema::enableForeignKeyConstraints();

        $now = now();

        DB::table('districts')->insert([
            ['name' => 'Barguna', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Barisal', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Bhola', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Jhalokati', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Patuakhali', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Pirojpur', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Bandarban', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Brahmanbaria', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Chandpur', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Chittagong', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Comilla', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Coxs Bazar', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Feni', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Khagrachhari', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Lakshmipur', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Noakhali', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Rangamati', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Dhaka', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Faridpur', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Gazipur', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Gopalganj', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Jamalpur', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Kishoreganj', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Madaripur', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Manikganj', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Munshiganj', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Mymensingh', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Narayanganj', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Narsingdi', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Netrakona', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Rajbari', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Shariatpur', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sherpur', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Tangail', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Bagerhat', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Chuadanga', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Jessore', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Jhenaidah', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Khulna', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Kushtia', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Magura', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Meherpur', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Narail', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Satkhira', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Bogra', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Joypurhat', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Naogaon', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Natore', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Nawabganj', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Pabna', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Rajshahi', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sirajganj', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Dinajpur', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Gaibandha', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Kurigram', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Lalmonirhat', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Nilphamari', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Panchagarh', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Rangpur', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Thakurgaon', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Habiganj', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Moulvibazar', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sunamganj', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sylhet', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
