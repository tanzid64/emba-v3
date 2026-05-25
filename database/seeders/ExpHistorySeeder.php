<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\ExpHistory;
use Illuminate\Database\Seeder;

class ExpHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $applicant = Applicant::where('email', 'tanzid3@gmail.com')->firstOrFail();

        $records = [
            [
                'organization' => 'Digiwinners',
                'designation' => 'Junior Software Developer',
                'duration' => '1 year',
                'total_experience' => 1.00,
            ],
            [
                'organization' => 'Consultech Solutions BD',
                'designation' => 'Software Developer',
                'duration' => '8 Months',
                'total_experience' => 0.80,
            ],
            [
                'organization' => 'Kiwii IT',
                'designation' => 'Laravel Developer',
                'duration' => '1 Year',
                'total_experience' => 1.00,
            ],
        ];

        foreach ($records as $record) {
            ExpHistory::updateOrCreate(
                [
                    'applicant_id' => $applicant->id,
                    'organization' => $record['organization'],
                ],
                $record,
            );
        }
    }
}
