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

    // Forfeit is a closed status, so it only appears with showClosed.
    $this->actingAs($staff)
        ->get(route('training-assignments.index', ['showClosed' => 'on']))
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

test('the row menus are positioned in viewport coordinates, not document ones', function () {
    $student = User::factory()->create(['rostered' => true]);
    trainingAssignmentWithStatus($student, TrainingStatus::ACTIVE);

    $staff = User::factory()->create();
    $staff->assignRole('training');
    $staff->givePermissionTo('view dashboard');

    // Both menus in this table position with `fixed`, whose coordinate space is
    // already the one getBoundingClientRect() reports. Adding a scroll offset
    // double-counts it and strands the menu down the page.
    $this->actingAs($staff)
        ->get(route('training-assignments.index'))
        ->assertOk()
        ->assertSee('getBoundingClientRect', false)
        ->assertDontSee('window.scrollY', false)
        ->assertDontSee('window.scrollX', false);
});
