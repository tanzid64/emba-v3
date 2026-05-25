<?php

namespace Database\Seeders;

use App\Enums\BloodGroup;
use App\Enums\GenderEnum;
use App\Enums\MaritalStatus;
use App\Enums\ReligionEnum;
use App\Models\Applicant;
use App\Models\ApplicantProfile;
use Illuminate\Database\Seeder;

class ApplicantProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $applicant = Applicant::where('email', 'tanzid3@gmail.com')->firstOrFail();

        ApplicantProfile::updateOrCreate(
            ['applicant_id' => $applicant->id],
            [
                'batch_id' => $applicant->batch_id,
                'full_name' => 'MD TANZID HAQUE',
                'father_name' => 'MD FAZLUL HAQUE',
                'mother_name' => 'MST DELUARA BEGUM',
                'date_of_birth' => '1998-03-11',
                'photo' => null,
                'gender' => GenderEnum::MALE,
                'blood_group' => BloodGroup::B_POSITIVE,
                'religion' => ReligionEnum::ISLAM,
                'marital_status' => MaritalStatus::SINGLE,
                'nationality' => 'Bangladeshi',
                'tot_year_of_schooling' => 18.00,
                'tot_year_of_exp' => 2.80,
            ],
        );
    }
}
