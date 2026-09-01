<?php

use App\Enums\TrainingStatus;
use App\Enums\TrainingType;
use App\Models\TrainingAssignment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

test('a manager can deactivate another users training assignment', function () {
    $manager = User::factory()->create();
    $manager->givePermissionTo('view dashboard');  // admin group middleware
    $manager->assignRole('training');              // /training group middleware
    $manager->givePermissionTo('manage students'); // non-owner deactivate path

    $student = User::factory()->create(['rostered' => true]);
    $assignment = TrainingAssignment::create([
        'user_id' => $student->id,
        'instructor_id' => null,
        'training_type' => TrainingType::S1->value,
        'status' => TrainingStatus::ACTIVE->value,
        'active' => true,
    ]);

    $this->actingAs($manager)
        ->delete(route('training-assignments.destroy'), ['id' => (string) $assignment->id])
        ->assertRedirect();

    $assignment->refresh();

    // Enum status persists without tripping a string mutator, and the row is deactivated.
    expect($assignment->active)->toBeFalse()
        ->and($assignment->status)->toBe(TrainingStatus::FORFEIT);
});
