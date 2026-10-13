<?php

namespace Database\Factories;

use App\Models\CertificationFacility;
use App\Models\CertificationLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificationLevel>
 */
class CertificationLevelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'facility_id' => CertificationFacility::factory(),
            'level' => fake()->unique()->numberBetween(1, 100000),
            'name' => fake()->unique()->jobTitle(),
            'abbreviation' => strtoupper(fake()->unique()->lexify('??')),
        ];
    }
}
