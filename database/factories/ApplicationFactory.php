<?php

namespace Database\Factories;

use App\Enum\ApplicationStatusEnum;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Batch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'applicant_id' => Applicant::factory(),
            'batch_id' => Batch::factory(),
            'status' => ApplicationStatusEnum::Pending,
        ];
    }
}
