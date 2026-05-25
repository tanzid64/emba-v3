<?php

namespace Database\Seeders;

use App\Enums\DegreeType;
use App\Models\Applicant;
use App\Models\EducationHistory;
use Illuminate\Database\Seeder;

class EducationHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $applicant = Applicant::where('email', 'tanzid3@gmail.com')->firstOrFail();

        $records = [
            [
                'type' => DegreeType::SSC,
                'name' => 'S.S.C / Equivalent',
                'major' => 'Business Studies',
                'institute' => 'Dhaka',
                'result' => '4.88',
                'scale' => '5.00',
                'passing_year' => 2013,
                'duration' => 10,
            ],
            [
                'type' => DegreeType::HSC,
                'name' => 'H.S.C / Equivalent',
                'major' => 'Technical / Vocational',
                'institute' => 'Technical',
                'result' => '2.93',
                'scale' => '4.00',
                'passing_year' => 2017,
                'duration' => 4,
            ],
            [
                'type' => DegreeType::UNDERGRADUATE,
                'name' => 'Honours / Degree',
                'major' => 'EEE',
                'institute' => 'Green University of Bangladesh',
                'result' => '3.49',
                'scale' => '4.00',
                'passing_year' => 2023,
                'duration' => 4,
            ],
        ];

        foreach ($records as $record) {
            EducationHistory::updateOrCreate(
                [
                    'applicant_id' => $applicant->id,
                    'type' => $record['type']->value,
                ],
                $record,
            );
        }
    }
}
