<?php

use App\Enums\TrainingStatus;
use App\Enums\TrainingType;
use App\Models\TrainingAssignment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function trainingAssignmentWithStatus(User $student, TrainingStatus $status): TrainingAssignment
{
    return TrainingAssignment::create([
        'user_id' => $student->id,
        'training_type' => TrainingType::cases()[0],
        'status' => $status,
        'active' => true,
    ]);
}

test('the training assignments table renders the status as a colored badge', function () {
    $student = User::factory()->create(['rostered' => true]);
    trainingAssignmentWithStatus($student, TrainingStatus::FORFEIT);

    $staff = User::factory()->create();
    $staff->assignRole('training');
    $staff->givePermissionTo('view dashboard');

    $this->actingAs($staff)
        ->get(route('training-assignments.index'))
        ->assertOk()
        ->assertSee('badge-error', false)
        ->assertSee('Forfeit');
});

test('a users own training assignments show the badge too', function () {
    $student = User::factory()->create(['rostered' => true]);
    trainingAssignmentWithStatus($student, TrainingStatus::ACTIVE);

    $this->actingAs($student)
        ->get(route('users.show.training-assignments', $student))
        ->assertOk()
        ->assertSee('badge-success', false)
        ->assertSee('Active');
});
