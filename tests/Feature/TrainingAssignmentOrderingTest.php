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
function headingOrder(string $html): array
{
    preg_match_all('/<th colspan="8"[^>]*>\s*([^<]+?)\s*<span/', $html, $matches);

    return array_map('trim', $matches[1]);
}

test('the table lists assignments oldest request first', function () {
    // Inserted in neither the wanted order nor its reverse, so passing cannot be
    // an accident of insertion sequence or of the id-desc default.
    $middle = makeAssignment('2026-01-05 00:00:00');
    $oldest = makeAssignment('2026-01-01 00:00:00');
    $newest = makeAssignment('2026-01-09 00:00:00');

    $html = $this->actingAs(orderingStaff())
        ->get(route('training-assignments.index'))
        ->assertOk()
        ->getContent();

    $positions = array_map(
        fn (TrainingAssignment $a) => strpos($html, 'users/'.$a->user_id),
        [$oldest, $middle, $newest]
    );

    expect($positions)->not->toContain(false);
    expect($positions)->toBe(array_values(collect($positions)->sort()->all()));
});

test('ordering is applied before pagination, not to one page', function () {
    // 21 rows = two pages at 20/page. The oldest row is inserted last, so only a
    // pre-pagination sort can pull it onto page one.
    foreach (range(1, 20) as $i) {
        makeAssignment('2026-02-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).' 00:00:00');
    }

    $oldest = makeAssignment('2026-01-01 00:00:00');
    $newest = TrainingAssignment::orderByDesc('created_at')->first();

    $html = $this->actingAs(orderingStaff())
        ->get(route('training-assignments.index'))
        ->assertOk()
        ->getContent();

    // Oldest is on page one; the newest has been pushed onto page two.
    expect(strpos($html, 'users/'.$oldest->user_id))->not->toBeFalse();
    expect(strpos($html, 'users/'.$newest->user_id))->toBeFalse();
});

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

test('rows are grouped under one heading per training type, C1 first', function () {
    $types = [
        TrainingType::S1, TrainingType::C1, TrainingType::MCO_GND,
        TrainingType::MCO_APP, TrainingType::S3, TrainingType::MCO_TWR,
        TrainingType::S2, TrainingType::C1,
    ];

    foreach ($types as $i => $type) {
        makeTypedAssignment($type, '2026-01-0'.($i + 1).' 00:00:00');
    }

    $html = $this->actingAs(orderingStaff())
        ->get(route('training-assignments.index'))
        ->assertOk()
        ->getContent();

    // Headings appear in course-seniority order, each exactly once.
    $this->assertSame(
        ['C1', 'F11 TRACON', 'S3', 'MCO TWR', 'S2', 'MCO GND', 'S1'],
        headingOrder($html)
    );
});

test('a heading reports how many rows sit under it', function () {
    makeTypedAssignment(TrainingType::C1, '2026-01-01 00:00:00');
    makeTypedAssignment(TrainingType::C1, '2026-01-02 00:00:00');
    makeTypedAssignment(TrainingType::S1, '2026-01-03 00:00:00');

    $html = $this->actingAs(orderingStaff())
        ->get(route('training-assignments.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('C1')
        ->and(preg_match('/C1\s*<span[^>]*>\(2\)/', $html))->toBe(1)
        ->and(preg_match('/S1\s*<span[^>]*>\(1\)/', $html))->toBe(1);
});

test('within a group the oldest request is listed first', function () {
    $newer = makeTypedAssignment(TrainingType::C1, '2026-02-01 00:00:00');
    $older = makeTypedAssignment(TrainingType::C1, '2026-01-01 00:00:00');

    $html = $this->actingAs(orderingStaff())
        ->get(route('training-assignments.index'))
        ->assertOk()
        ->getContent();

    expect(strpos($html, 'users/'.$older->user_id))
        ->toBeLessThan(strpos($html, 'users/'.$newer->user_id));
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

function makeStatusAssignment(TrainingType $type, TrainingStatus $status, string $createdAt): TrainingAssignment
{
    $assignment = TrainingAssignment::create([
        'user_id' => User::factory()->create(['rostered' => true])->id,
        'training_type' => $type,
        'status' => $status,
        'active' => true,
    ]);

    $assignment->forceFill(['created_at' => $createdAt])->saveQuietly();

    return $assignment;
}

test('completed and forfeited assignments are hidden by default', function () {
    $open = makeStatusAssignment(TrainingType::C1, TrainingStatus::ACTIVE, '2026-01-01 00:00:00');
    $complete = makeStatusAssignment(TrainingType::C1, TrainingStatus::COMPLETE, '2026-01-02 00:00:00');
    $forfeit = makeStatusAssignment(TrainingType::S1, TrainingStatus::FORFEIT, '2026-01-03 00:00:00');

    $this->actingAs(orderingStaff())
        ->get(route('training-assignments.index'))
        ->assertOk()
        ->assertSee('users/'.$open->user_id, false)
        ->assertDontSee('users/'.$complete->user_id, false)
        ->assertDontSee('users/'.$forfeit->user_id, false)
        ->assertDontSee('Completed / Forfeited');
});

test('the checkbox reveals them in one section at the bottom', function () {
    $open = makeStatusAssignment(TrainingType::C1, TrainingStatus::ACTIVE, '2026-01-01 00:00:00');
    $s1 = makeStatusAssignment(TrainingType::S1, TrainingStatus::ACTIVE, '2026-01-02 00:00:00');
    $complete = makeStatusAssignment(TrainingType::C1, TrainingStatus::COMPLETE, '2026-01-03 00:00:00');
    $forfeit = makeStatusAssignment(TrainingType::S1, TrainingStatus::FORFEIT, '2026-01-04 00:00:00');

    $html = $this->actingAs(orderingStaff())
        ->get(route('training-assignments.index', ['showClosed' => 'on']))
        ->assertOk()
        ->getContent();

    // One combined heading, listed after every training-type heading.
    expect(headingOrder($html))->toBe(['C1', 'S1', 'Completed / Forfeited']);

    // ...and the closed rows sit under it, below the open ones.
    expect(strpos($html, 'users/'.$open->user_id))
        ->toBeLessThan(strpos($html, 'users/'.$s1->user_id));
    expect(strpos($html, 'users/'.$s1->user_id))
        ->toBeLessThan(strpos($html, 'users/'.$complete->user_id));
    expect(strpos($html, 'users/'.$forfeit->user_id))->not->toBeFalse();
});

test('a closed assignment is not split out of its training type group when open', function () {
    // A Complete C1 must not create a second "C1" heading; it belongs to the
    // closed section regardless of its type.
    makeStatusAssignment(TrainingType::C1, TrainingStatus::ACTIVE, '2026-01-01 00:00:00');
    makeStatusAssignment(TrainingType::C1, TrainingStatus::COMPLETE, '2026-01-02 00:00:00');

    $html = $this->actingAs(orderingStaff())
        ->get(route('training-assignments.index', ['showClosed' => 'on']))
        ->assertOk()
        ->getContent();

    expect(headingOrder($html))->toBe(['C1', 'Completed / Forfeited']);
});

test('the closed filter survives pagination', function () {
    $paginator = $this->actingAs(orderingStaff())
        ->get(route('training-assignments.index', ['showClosed' => 'on']))
        ->assertOk()
        ->viewData('trainingAssignments');

    expect($paginator->url(2))->toContain('showClosed=on');
});

test('only COMPLETE and FORFEIT count as closed', function () {
    $closed = array_map(fn (TrainingStatus $s) => $s->name, TrainingStatus::closed());

    expect($closed)->toBe(['COMPLETE', 'FORFEIT']);

    foreach (TrainingStatus::cases() as $case) {
        expect($case->isClosed())->toBe(in_array($case->name, $closed, true));
    }
});

test('striping restarts per group and is skipped for a lone row', function () {
    // C1 gets three rows, MCO GND exactly one.
    makeStatusAssignment(TrainingType::C1, TrainingStatus::ACTIVE, '2026-01-01 00:00:00');
    makeStatusAssignment(TrainingType::C1, TrainingStatus::ACTIVE, '2026-01-02 00:00:00');
    makeStatusAssignment(TrainingType::C1, TrainingStatus::ACTIVE, '2026-01-03 00:00:00');
    makeStatusAssignment(TrainingType::MCO_GND, TrainingStatus::ACTIVE, '2026-01-04 00:00:00');

    $html = $this->actingAs(orderingStaff())
        ->get(route('training-assignments.index'))
        ->assertOk()
        ->getContent();

    // Only the 2nd row of the three-row group is striped; the lone row is not.
    expect(substr_count($html, '<tr class="bg-base-200">'))->toBe(1);

    // Sanity: daisyUI's own zebra class is not doing the striping.
    expect($html)->not->toContain('table-zebra');
});
