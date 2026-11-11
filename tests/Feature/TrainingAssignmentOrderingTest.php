<?php

use App\Enums\TrainingStatus;
use App\Enums\TrainingType;
use App\Models\TrainingAssignment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function orderingStaff(): User
{
    $staff = User::factory()->create();
    $staff->assignRole('training');
    $staff->givePermissionTo('view dashboard');

    return $staff;
}

function makeAssignment(string $createdAt): TrainingAssignment
{
    $assignment = TrainingAssignment::create([
        'user_id' => User::factory()->create(['rostered' => true])->id,
        'training_type' => TrainingType::S1,
        'status' => TrainingStatus::ACTIVE,
        'active' => true,
    ]);

    $assignment->forceFill(['created_at' => $createdAt])->saveQuietly();

    return $assignment;
}

function makeTypedAssignment(TrainingType $type, string $createdAt): TrainingAssignment
{
    $assignment = TrainingAssignment::create([
        'user_id' => User::factory()->create(['rostered' => true])->id,
        'training_type' => $type,
        'status' => TrainingStatus::ACTIVE,
        'active' => true,
    ]);

    $assignment->forceFill(['created_at' => $createdAt])->saveQuietly();

    return $assignment;
}

/**
 * The training-type headings, in the order they appear in the rendered table.
 *
 * @return array<int, string>
 */
test('inactive assignments are listed too, and counted in the total', function () {
    $active = makeAssignment('2026-01-01 00:00:00');
    $inactive = makeAssignment('2026-01-02 00:00:00');
    $inactive->update(['active' => false]);

    $this->actingAs(orderingStaff())
        ->get(route('training-assignments.index'))
        ->assertOk()
        ->assertSee('users/'.$active->user_id, false)
        ->assertSee('users/'.$inactive->user_id, false);
});

test('the removed show-inactive parameter is simply ignored', function () {
    makeAssignment('2026-01-01 00:00:00');
    makeAssignment('2026-01-02 00:00:00')->update(['active' => false]);

    // An old bookmark or link carrying ?showInactive=on must not 422 on the
    // validator now that the rule is gone.
    $this->actingAs(orderingStaff())
        ->get(route('training-assignments.index', ['showInactive' => 'on']))
        ->assertOk();
});

test('pagination links keep the active filters', function () {
    foreach (range(1, 25) as $i) {
        makeTypedAssignment(TrainingType::C1, '2026-01-01 00:00:00');
    }

    // Asserted on the paginator rather than scraped out of the HTML: Livewire's
    // WithPagination sets Paginator::defaultView() statically, so once any
    // Livewire component has rendered in this process the markup switches to
    // wire:click links with no href at all.
    $paginator = $this->actingAs(orderingStaff())
        ->get(route('training-assignments.index', ['trainingType' => TrainingType::C1->value]))
        ->assertOk()
        ->viewData('trainingAssignments');

    expect($paginator->hasPages())->toBeTrue()
        ->and($paginator->url(2))->toContain('trainingType=4')
        ->and($paginator->url(2))->toContain('page=2');
});

test('the training type filter tolerates the empty "None" option', function () {
    makeTypedAssignment(TrainingType::C1, '2026-01-01 00:00:00');
    makeTypedAssignment(TrainingType::S1, '2026-01-02 00:00:00');

    // "None" submits trainingType= (empty). Must list everything, not 500 on a
    // `where('training_type', '')` against an integer column.
    $this->actingAs(orderingStaff())
        ->get(route('training-assignments.index', ['trainingType' => '']))
        ->assertOk();
});

test('the training type filter narrows to one type when set', function () {
    $c1 = makeTypedAssignment(TrainingType::C1, '2026-01-01 00:00:00');
    $s1 = makeTypedAssignment(TrainingType::S1, '2026-01-02 00:00:00');

    $this->actingAs(orderingStaff())
        ->get(route('training-assignments.index', ['trainingType' => TrainingType::C1->value]))
        ->assertOk()
        ->assertSee('users/'.$c1->user_id, false)
        ->assertDontSee('users/'.$s1->user_id, false);
});
