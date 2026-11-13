<?php

use App\Enums\TrainingStatus;
use App\Enums\TrainingType;
use App\Livewire\TrainingAssignmentStatus;
use App\Models\TrainingAssignment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function inlineStatusAssignment(TrainingStatus $status = TrainingStatus::ACTIVE): TrainingAssignment
{
    return TrainingAssignment::create([
        'user_id' => User::factory()->create(['rostered' => true])->id,
        'training_type' => TrainingType::cases()[0],
        'status' => $status,
        'active' => true,
    ]);
}

test('picking a status from the menu saves it in one call', function () {
    $assignment = inlineStatusAssignment(TrainingStatus::ACTIVE);
    $this->actingAs(User::factory()->create()->assignRole('instructor'));

    Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $assignment])
        ->call('setStatus', TrainingStatus::MOCK->value)
        ->assertHasNoErrors();

    expect($assignment->fresh()->status)->toBe(TrainingStatus::MOCK);
});

test('the menu offers every status, marking the current one', function () {
    $assignment = inlineStatusAssignment(TrainingStatus::SOLO);
    $this->actingAs(User::factory()->create()->assignRole('instructor'));

    $component = Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $assignment]);

    foreach (TrainingStatus::cases() as $case) {
        $component->assertSeeHtml('wire:click="setStatus('.$case->value.')"');
    }

    // The badge itself renders the current status; the menu row for it is the
    // only one flagged selected.
    expect(substr_count($component->html(), 'aria-selected="true"'))->toBe(1);
    $component->assertSeeHtml('aria-selected="true"');
});

test('re-picking the current status is a no-op', function () {
    $assignment = inlineStatusAssignment(TrainingStatus::ACTIVE);
    $this->actingAs(User::factory()->create()->assignRole('instructor'));

    $before = $assignment->updated_at;

    Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $assignment])
        ->call('setStatus', TrainingStatus::ACTIVE->value)
        ->assertHasNoErrors();

    expect($assignment->fresh()->status)->toBe(TrainingStatus::ACTIVE);
    expect($assignment->fresh()->updated_at->eq($before))->toBeTrue();
});

test('a status outside the enum is rejected', function () {
    $assignment = inlineStatusAssignment(TrainingStatus::ACTIVE);
    $this->actingAs(User::factory()->create()->assignRole('instructor'));

    Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $assignment])
        ->call('setStatus', 99)
        ->assertHasErrors('status');

    expect($assignment->fresh()->status)->toBe(TrainingStatus::ACTIVE);
});

test('changing the status inline does not email the student', function () {
    Mail::fake();

    $assignment = inlineStatusAssignment(TrainingStatus::ACTIVE);
    $this->actingAs(User::factory()->create()->assignRole('instructor'));

    Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $assignment])
        ->call('setStatus', TrainingStatus::COMPLETE->value);

    Mail::assertNothingQueued();
});

test('the cell refuses to mount without manage students', function () {
    $assignment = inlineStatusAssignment();
    $this->actingAs(User::factory()->create()->assignRole('training'));

    Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $assignment])
        ->assertForbidden();
});

test('a save is refused once the permission is gone', function () {
    $assignment = inlineStatusAssignment(TrainingStatus::ACTIVE);
    $instructor = User::factory()->create()->assignRole('instructor');
    $this->actingAs($instructor);

    $component = Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $assignment]);

    $instructor->removeRole('instructor');

    $component->call('setStatus', TrainingStatus::FORFEIT->value)->assertForbidden();

    expect($assignment->fresh()->status)->toBe(TrainingStatus::ACTIVE);
});

test('the table renders the editable cell for instructors and a plain badge otherwise', function () {
    $assignment = inlineStatusAssignment(TrainingStatus::SOLO);

    $instructor = User::factory()->create();
    $instructor->assignRole(['instructor', 'training']);
    $instructor->givePermissionTo('view dashboard');

    $this->actingAs($instructor)
        ->get(route('training-assignments.index'))
        ->assertOk()
        ->assertSee('Click to change status');

    $trainingStaff = User::factory()->create();
    $trainingStaff->assignRole('training');
    $trainingStaff->givePermissionTo('view dashboard');

    $this->actingAs($trainingStaff)
        ->get(route('training-assignments.index'))
        ->assertOk()
        ->assertSee('badge-info', false)
        ->assertDontSee('Click to change status');
});

test('an inline change is recorded in the activity log', function () {
    $assignment = inlineStatusAssignment(TrainingStatus::ACTIVE);
    $instructor = User::factory()->create()->assignRole('instructor');
    $this->actingAs($instructor);

    Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $assignment])
        ->call('setStatus', TrainingStatus::CHECKOUT->value);

    $activity = Activity::query()
        ->where('subject_type', TrainingAssignment::class)
        ->where('subject_id', $assignment->id)
        ->where('description', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($instructor->id);
    expect($activity->properties['attributes']['status'])->toBe(TrainingStatus::CHECKOUT->value);
});

test('the status menu is wired to reposition itself inside the viewport', function () {
    $assignment = inlineStatusAssignment(TrainingStatus::ACTIVE);
    $this->actingAs(User::factory()->create()->assignRole('instructor'));

    // The clamping itself is browser geometry that Pest cannot exercise; this
    // only guards the wiring it depends on — a ref to measure, and a deferred
    // measurement, since x-show leaves the menu unsized until it is shown.
    $html = Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $assignment])->html();

    expect($html)
        ->toContain('x-ref="menu"')
        ->toContain('$nextTick')
        ->toContain('window.innerWidth')
        ->toContain('window.innerHeight');
});

test('crossing the open/closed line asks the page to reload', function () {
    $assignment = inlineStatusAssignment(TrainingStatus::ACTIVE);
    $this->actingAs(User::factory()->create()->assignRole('instructor'));

    // Active -> Complete: the row leaves its training-type group.
    Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $assignment])
        ->call('setStatus', TrainingStatus::COMPLETE->value)
        ->assertDispatched('training-assignment-moved');

    // Complete -> Active: and back again.
    Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $assignment->fresh()])
        ->call('setStatus', TrainingStatus::ACTIVE->value)
        ->assertDispatched('training-assignment-moved');
});

test('moving between two open statuses does not reload', function () {
    $assignment = inlineStatusAssignment(TrainingStatus::ACTIVE);
    $this->actingAs(User::factory()->create()->assignRole('instructor'));

    Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $assignment])
        ->call('setStatus', TrainingStatus::MOCK->value)
        ->assertNotDispatched('training-assignment-moved');

    expect($assignment->fresh()->status)->toBe(TrainingStatus::MOCK);
});

test('moving between the two closed statuses does not reload', function () {
    $assignment = inlineStatusAssignment(TrainingStatus::COMPLETE);
    // A closed row carries active = false, as every writer now leaves it.
    $assignment->update(['active' => false]);
    $this->actingAs(User::factory()->create()->assignRole('instructor'));

    // Both sit in the same section and neither touches the flag, so nothing
    // outside this component changes.
    Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $assignment])
        ->call('setStatus', TrainingStatus::FORFEIT->value)
        ->assertNotDispatched('training-assignment-moved');
});

test('re-picking the same status dispatches nothing and writes nothing', function () {
    $assignment = inlineStatusAssignment(TrainingStatus::ACTIVE);
    $this->actingAs(User::factory()->create()->assignRole('instructor'));

    $before = $assignment->updated_at;

    Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $assignment])
        ->call('setStatus', TrainingStatus::ACTIVE->value)
        ->assertNotDispatched('training-assignment-moved');

    expect($assignment->fresh()->updated_at->eq($before))->toBeTrue();
});

test('closing an assignment inline deactivates it', function () {
    $assignment = inlineStatusAssignment(TrainingStatus::CHECKOUT);
    $this->actingAs(User::factory()->create()->assignRole('instructor'));

    Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $assignment])
        ->call('setStatus', TrainingStatus::COMPLETE->value)
        ->assertHasNoErrors();

    // Otherwise create() would keep refusing the student's next request, on the
    // grounds that they still have an active assignment.
    expect($assignment->fresh()->active)->toBeFalse();
});

test('forfeiting an assignment inline deactivates it', function () {
    $assignment = inlineStatusAssignment(TrainingStatus::ACTIVE);
    $this->actingAs(User::factory()->create()->assignRole('instructor'));

    Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $assignment])
        ->call('setStatus', TrainingStatus::FORFEIT->value);

    expect($assignment->fresh()->active)->toBeFalse();
});

test('reopening a closed assignment inline reactivates it', function () {
    $assignment = inlineStatusAssignment(TrainingStatus::FORFEIT);
    $assignment->update(['active' => false]);
    $this->actingAs(User::factory()->create()->assignRole('instructor'));

    Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $assignment])
        ->call('setStatus', TrainingStatus::ACTIVE->value)
        ->assertHasNoErrors();

    // Without this the row returns to the queue but claim()/drop() both refuse
    // it with "Cannot update inactive training assignment."
    expect($assignment->fresh()->active)->toBeTrue();
});

test('an inline change heals a row left inconsistent by the old Active checkbox', function () {
    $assignment = inlineStatusAssignment(TrainingStatus::ACTIVE);
    $assignment->update(['active' => false]);
    $this->actingAs(User::factory()->create()->assignRole('instructor'));

    Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $assignment])
        ->call('setStatus', TrainingStatus::SOLO->value);

    // Open status with active = false is no longer a state the app can produce,
    // but rows predating the checkbox's removal still carry it, and they sit in
    // the queue refusing claim() and drop() until something sets the flag.
    expect($assignment->fresh()->active)->toBeTrue();
});

test('reopening a row is refused when the student already has a live assignment', function () {
    $student = User::factory()->create(['rostered' => true]);

    $forfeited = TrainingAssignment::create([
        'user_id' => $student->id,
        'training_type' => TrainingType::S2,
        'status' => TrainingStatus::FORFEIT,
        'active' => false,
    ]);

    // The student moved on and requested something else.
    TrainingAssignment::create([
        'user_id' => $student->id,
        'training_type' => TrainingType::C1,
        'status' => TrainingStatus::ACTIVE,
        'active' => true,
    ]);

    $this->actingAs(User::factory()->create()->assignRole('instructor'));

    Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $forfeited])
        ->call('setStatus', TrainingStatus::ACTIVE->value)
        ->assertHasErrors('status');

    // Two live rows would let two trainers claim the same student and would
    // sidestep create()'s one-active-assignment rule.
    expect($forfeited->fresh()->status)->toBe(TrainingStatus::FORFEIT)
        ->and($forfeited->fresh()->active)->toBeFalse();
});

test('reopening is allowed when the student has no other live assignment', function () {
    $assignment = inlineStatusAssignment(TrainingStatus::FORFEIT);
    $assignment->update(['active' => false]);
    $this->actingAs(User::factory()->create()->assignRole('instructor'));

    Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $assignment])
        ->call('setStatus', TrainingStatus::ACTIVE->value)
        ->assertHasNoErrors();

    expect($assignment->fresh()->active)->toBeTrue();
});

test('a closed sibling does not block reopening', function () {
    $student = User::factory()->create(['rostered' => true]);

    $first = TrainingAssignment::create([
        'user_id' => $student->id,
        'training_type' => TrainingType::S2,
        'status' => TrainingStatus::FORFEIT,
        'active' => false,
    ]);

    TrainingAssignment::create([
        'user_id' => $student->id,
        'training_type' => TrainingType::S1,
        'status' => TrainingStatus::COMPLETE,
        'active' => false,
    ]);

    $this->actingAs(User::factory()->create()->assignRole('instructor'));

    Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $first])
        ->call('setStatus', TrainingStatus::ACTIVE->value)
        ->assertHasNoErrors();

    expect($first->fresh()->active)->toBeTrue();
});

test('waking a parked row reloads the page even between two open statuses', function () {
    $assignment = inlineStatusAssignment(TrainingStatus::ACTIVE);
    $assignment->update(['active' => false]);
    $this->actingAs(User::factory()->create()->assignRole('instructor'));

    // The row keeps its section, but its Inactive marker, its Claim action and
    // its place in the counters are all rendered by the table, not here.
    Livewire::test(TrainingAssignmentStatus::class, ['assignment' => $assignment])
        ->call('setStatus', TrainingStatus::SOLO->value)
        ->assertDispatched('training-assignment-moved');
});
