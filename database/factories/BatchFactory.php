<?php

namespace Database\Factories;

use App\Enum\BatchStatusEnum;
use App\Models\Batch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = $this->faker->year();

        return [
            'name' => 'EMBA Batch '.$this->faker->unique()->numberBetween(1, 100),
            'code' => 'EMBA-'.$year.'-'.$this->faker->unique()->numberBetween(1, 99),
            'admission_year' => $year,
            'status' => BatchStatusEnum::DRAFT,
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => BatchStatusEnum::OPEN]);
    }

    public function closed(): static
    {
        return $this->state(['status' => BatchStatusEnum::CLOSED]);
    }
}
