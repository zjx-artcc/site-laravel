<?php

use App\Enums\TrainingStatus;
use App\Enums\TrainingType;
use App\Models\TrainingAssignment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Mail::fake();
});

/**
 * `layouts.admin` stopped extending `layouts.main` and never got its own copy
 * of the alert block, so every `with('success')` and `with('error')` on every
 * admin page was silently swallowed. Both layouts now include `x-alerts`.
 */
function alertStaff(): User
{
    $staff = User::factory()->create();
    $staff->assignRole('training');
    $staff->assignRole('instructor');
    $staff->givePermissionTo('view dashboard');

    return $staff;
}

test('an admin page renders a flash success message', function () {
    $assignment = TrainingAssignment::create([
        'user_id' => User::factory()->create(['rostered' => true])->id,
        'training_type' => TrainingType::S1,
        'status' => TrainingStatus::ACTIVE,
        'active' => true,
    ]);

    $this->actingAs(alertStaff())->put(
        route('training-assignments.update', ['assignment' => $assignment->id]),
        [
            'instructorId' => null,
            'status' => TrainingStatus::SOLO->value,
            'trainingType' => TrainingType::S1->value,
        ]
    );

    $this->actingAs(alertStaff())
        ->get(route('training-assignments.index'))
        ->assertOk()
        ->assertSee('Training request updated successfully');
});

test('an admin page renders a flash error message', function () {
    $this->actingAs(alertStaff())
        ->withSession(['error' => 'Cannot update inactive training assignment.'])
        ->get(route('training-assignments.index'))
        ->assertOk()
        ->assertSee('Cannot update inactive training assignment.');
});

test('a public page still renders its alerts', function () {
    $this->withSession(['success' => 'Public page alert'])
        ->get(route('faq.index'))
        ->assertOk()
        ->assertSee('Public page alert');
});
