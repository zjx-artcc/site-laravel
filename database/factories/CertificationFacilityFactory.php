<?php

namespace Database\Factories;

use App\Models\CertificationFacility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificationFacility>
 */
class CertificationFacilityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'identifier' => strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->unique()->company(),
            'order' => fake()->numberBetween(0, 100),
        ];
    }
}
