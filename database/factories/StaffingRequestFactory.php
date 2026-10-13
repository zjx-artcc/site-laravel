<?php

namespace Database\Factories;

use App\Models\StaffingRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffingRequest>
 */
class StaffingRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true).' Fly-In',
            'description' => fake()->paragraph(),
            'requested_at' => fake()->dateTimeBetween('now', '+2 months'),
            'closed' => false,
        ];
    }
}
