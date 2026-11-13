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

function editingStaff(): User
{
    $staff = User::factory()->create();
    $staff->assignRole('training');
    $staff->assignRole('instructor');
    $staff->givePermissionTo('view dashboard');

    return $staff;
}

function flagAssignment(TrainingStatus $status, bool $active): TrainingAssignment
{
    return TrainingAssignment::create([
        'user_id' => User::factory()->create(['rostered' => true])->id,
        'training_type' => TrainingType::S1,
        'status' => $status,
        'active' => $active,
    ]);
}

/**
 * The fields the Edit form posts, now that Active is not one of them.
 *
 * @return array<string, mixed>
 */
function editPayload(TrainingAssignment $assignment, TrainingStatus $status): array
{
    return [
        'instructorId' => $assignment->instructor_id,
        'status' => $status->value,
        'trainingType' => $assignment->training_type->value,
    ];
}

test('the edit form no longer offers an Active checkbox', function () {
    $assignment = flagAssignment(TrainingStatus::ACTIVE, true);

    $this->actingAs(editingStaff())
        ->get(route('training-assignments.edit', ['assignment' => $assignment->id]))
        ->assertOk()
        ->assertDontSee('name="active"', false);
});

test('saving a closed status from the edit page deactivates the assignment', function () {
    $assignment = flagAssignment(TrainingStatus::ACTIVE, true);

    $this->actingAs(editingStaff())
        ->put(
            route('training-assignments.update', ['assignment' => $assignment->id]),
            editPayload($assignment, TrainingStatus::COMPLETE)
        );

    expect($assignment->fresh()->active)->toBeFalse();
});

test('saving an open status from the edit page reactivates the assignment', function () {
    $assignment = flagAssignment(TrainingStatus::FORFEIT, false);

    $this->actingAs(editingStaff())
        ->put(
            route('training-assignments.update', ['assignment' => $assignment->id]),
            editPayload($assignment, TrainingStatus::ACTIVE)
        );

    expect($assignment->fresh()->active)->toBeTrue();
});

test('a hand-posted active field is ignored', function () {
    $assignment = flagAssignment(TrainingStatus::ACTIVE, true);

    // The field is gone from the form, so a stale bookmark or a crafted request
    // must not be able to park an open assignment outside claim() and drop().
    $this->actingAs(editingStaff())
        ->put(
            route('training-assignments.update', ['assignment' => $assignment->id]),
            editPayload($assignment, TrainingStatus::ACTIVE) + ['active' => '0']
        );

    expect($assignment->fresh()->active)->toBeTrue();
});

test('every status maps to the expected active flag', function () {
    foreach (TrainingStatus::cases() as $status) {
        $assignment = flagAssignment(TrainingStatus::ACTIVE, true);

        $this->actingAs(editingStaff())
            ->put(
                route('training-assignments.update', ['assignment' => $assignment->id]),
                editPayload($assignment, $status)
            );

        expect($assignment->fresh()->active)
            ->toBe(! $status->isClosed(), $status->label().' should leave active = '.var_export(! $status->isClosed(), true));
    }
});

test('the edit page refuses to wake a parked row when the student is already training', function () {
    $student = User::factory()->create(['rostered' => true]);

    $parked = TrainingAssignment::create([
        'user_id' => $student->id,
        'training_type' => TrainingType::S2,
        'status' => TrainingStatus::ACTIVE,
        'active' => false,
    ]);

    TrainingAssignment::create([
        'user_id' => $student->id,
        'training_type' => TrainingType::C1,
        'status' => TrainingStatus::ACTIVE,
        'active' => true,
    ]);

    // Saving derives active from the status, so an edit touching only the
    // instructor would otherwise wake the parked row and give the student two.
    $this->actingAs(editingStaff())
        ->put(
            route('training-assignments.update', ['assignment' => $parked->id]),
            editPayload($parked, TrainingStatus::ACTIVE)
        )
        ->assertSessionHas('error');

    expect($parked->fresh()->active)->toBeFalse();
});

test('saving the edit page lands back on the queue', function () {
    $assignment = flagAssignment(TrainingStatus::ACTIVE, true);

    $this->actingAs(editingStaff())
        ->put(
            route('training-assignments.update', ['assignment' => $assignment->id]),
            editPayload($assignment, TrainingStatus::SOLO)
        )
        ->assertRedirect(route('training-assignments.index'))
        ->assertSessionHas('success');
});

test('the edit page rejects a status outside the enum', function () {
    $assignment = flagAssignment(TrainingStatus::ACTIVE, true);

    $this->actingAs(editingStaff())
        ->put(
            route('training-assignments.update', ['assignment' => $assignment->id]),
            ['instructorId' => null, 'status' => 99, 'trainingType' => TrainingType::S1->value]
        )
        ->assertSessionHasErrors('status');

    expect($assignment->fresh()->status)->toBe(TrainingStatus::ACTIVE);
});
