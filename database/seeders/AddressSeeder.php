<?php

namespace Database\Seeders;

use App\Enums\AddressTypeEnum;
use App\Models\Address;
use App\Models\Applicant;
use Illuminate\Database\Seeder;

class AddressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $applicant = Applicant::where('email', 'tanzid3@gmail.com')->firstOrFail();

        $sharedAttributes = [
            'care' => 'C/O Md. Tanzid Haque',
            'road' => 'House # 05, Road # 05, Mirpur -2',
            'district_id' => 18, // Dhaka
            'upazila_id' => 517, // Dhanmondi Thana
            'post_office' => 'Mirpur',
            'postal_code' => '1216',
        ];

        foreach ([AddressTypeEnum::PRESENT, AddressTypeEnum::PERMANENT] as $type) {
            Address::updateOrCreate(
                ['applicant_id' => $applicant->id, 'type' => $type->value],
                $sharedAttributes,
            );
        }
    }
}
