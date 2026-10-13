<?php

namespace Database\Factories;

use App\Enums\FeedbackExperience;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feedback>
 */
class FeedbackFactory extends Factory
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
            'controller_id' => User::factory(),
            'position' => 'JAX_'.fake()->randomElement(['CTR', 'APP', 'TWR', 'GND']),
            'experience' => fake()->randomElement(FeedbackExperience::cases()),
            'staff_followup' => false,
            'comments' => fake()->paragraph(),
        ];
    }
}
