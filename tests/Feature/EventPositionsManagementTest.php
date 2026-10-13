<?php

use App\Enums\EventType;
use App\Livewire\EventPositionsManagement;
use App\Models\Event;
use App\Models\EventPosition;
use App\Models\EventPositionPreset;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

test('loading a preset replaces the list and persists it immediately', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff', 'events');
    $this->actingAs($staff);

    $event = Event::create([
        'title' => 'Test Event',
        'description' => 'desc',
        'start' => now()->addDay(),
        'end' => now()->addDay()->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
        'presetPositions' => ['JAX_CTR'],
    ]);

    EventPositionPreset::create(['name' => 'Full Staffing', 'positions' => ['JAX_CTR', 'MCO_APP']]);

    Livewire::test(EventPositionsManagement::class, ['event' => $event])
        ->set('selectedPreset', 'Full Staffing')
        ->call('loadPreset')
        ->assertSet('positions', ['JAX_CTR', 'MCO_APP'])
        ->assertDispatched('notify', type: 'success');

    expect($event->fresh()->presetPositions)->toBe(['JAX_CTR', 'MCO_APP']);
});

test('save normalizes and persists the list', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff', 'events');
    $this->actingAs($staff);

    $event = Event::create([
        'title' => 'Test Event',
        'description' => 'desc',
        'start' => now()->addDay(),
        'end' => now()->addDay()->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
    ]);

    Livewire::test(EventPositionsManagement::class, ['event' => $event])
        ->set('positions', [' jax_ctr ', 'MCO_APP', 'mco_app'])
        ->call('save')
        ->assertSet('positions', ['JAX_CTR', 'MCO_APP'])
        ->assertDispatched('notify', type: 'success');

    expect($event->fresh()->presetPositions)->toBe(['JAX_CTR', 'MCO_APP']);
});

test('adding a position persists it immediately without a separate save', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff', 'events');
    $this->actingAs($staff);

    $event = Event::create([
        'title' => 'Test Event',
        'description' => 'desc',
        'start' => now()->addDay(),
        'end' => now()->addDay()->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
        'presetPositions' => ['JAX_CTR'],
    ]);

    Livewire::test(EventPositionsManagement::class, ['event' => $event])
        ->set('newPosition', 'mco_app')
        ->call('addPosition')
        ->assertSet('positions', ['JAX_CTR', 'MCO_APP'])
        ->assertDispatched('notify', type: 'success');

    expect($event->fresh()->presetPositions)->toBe(['JAX_CTR', 'MCO_APP']);
});

test('removing an unassigned position persists immediately without a separate save', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff', 'events');
    $this->actingAs($staff);

    $event = Event::create([
        'title' => 'Test Event',
        'description' => 'desc',
        'start' => now()->addDay(),
        'end' => now()->addDay()->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
        'presetPositions' => ['JAX_CTR', 'MCO_APP'],
    ]);

    Livewire::test(EventPositionsManagement::class, ['event' => $event])
        ->call('removePosition', 'MCO_APP')
        ->assertSet('positions', ['JAX_CTR'])
        ->assertDispatched('notify', type: 'success');

    expect($event->fresh()->presetPositions)->toBe(['JAX_CTR']);
});

test('a freeform removal of an assigned position is rejected', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff', 'events');
    $this->actingAs($staff);

    $event = Event::create([
        'title' => 'Test Event',
        'description' => 'desc',
        'start' => now()->addDay(),
        'end' => now()->addDay()->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
        'presetPositions' => ['JAX_CTR', 'MCO_APP'],
    ]);

    EventPosition::create([
        'event_id' => $event->id,
        'user_id' => User::factory()->create()->id,
        'requested_position' => 'MCO_APP',
        'assigned_position' => 'MCO_APP',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    Livewire::test(EventPositionsManagement::class, ['event' => $event])
        ->set('positions', ['JAX_CTR'])
        ->call('save')
        ->assertDispatched('notify', type: 'error')
        ->assertSet('positions', ['JAX_CTR', 'MCO_APP']);

    expect($event->fresh()->presetPositions)->toBe(['JAX_CTR', 'MCO_APP']);
});

test('loading a preset that would drop an assigned position is rejected and reverts', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff', 'events');
    $this->actingAs($staff);

    $event = Event::create([
        'title' => 'Test Event',
        'description' => 'desc',
        'start' => now()->addDay(),
        'end' => now()->addDay()->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
        'presetPositions' => ['JAX_CTR', 'MCO_APP'],
    ]);

    EventPosition::create([
        'event_id' => $event->id,
        'user_id' => User::factory()->create()->id,
        'requested_position' => 'MCO_APP',
        'assigned_position' => 'MCO_APP',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    EventPositionPreset::create(['name' => 'Narrow', 'positions' => ['JAX_CTR']]);

    Livewire::test(EventPositionsManagement::class, ['event' => $event])
        ->set('selectedPreset', 'Narrow')
        ->call('loadPreset')
        ->assertDispatched('notify', type: 'error')
        ->assertSet('positions', ['JAX_CTR', 'MCO_APP']); // reverted to DB truth

    expect($event->fresh()->presetPositions)->toBe(['JAX_CTR', 'MCO_APP']);
});

test('save requires the assign event positions permission', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('staff');
    $viewer->givePermissionTo('manage events');
    $this->actingAs($viewer);

    $event = Event::create([
        'title' => 'Test Event',
        'description' => 'desc',
        'start' => now()->addDay(),
        'end' => now()->addDay()->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
    ]);

    Livewire::test(EventPositionsManagement::class, ['event' => $event])
        ->set('positions', ['JAX_CTR'])
        ->call('save')
        ->assertForbidden();

    expect($event->fresh()->presetPositions)->toBeNull();
});

test('a manage events-only user gets a read-only positions component and cannot mutate it', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('staff');
    $viewer->givePermissionTo('manage events');
    $this->actingAs($viewer);

    $event = Event::create([
        'title' => 'Test Event',
        'description' => 'desc',
        'start' => now()->addDay(),
        'end' => now()->addDay()->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
        'presetPositions' => ['JAX_CTR'],
    ]);

    Livewire::test(EventPositionsManagement::class, ['event' => $event, 'readOnly' => true])
        ->assertSee('JAX_CTR')
        ->assertDontSee('Load Preset')
        ->call('addPosition')
        ->assertForbidden();
});

test('a drifted assignment outside the current list still blocks a save that omits it', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff', 'events');
    $this->actingAs($staff);

    $event = Event::create([
        'title' => 'Test Event',
        'description' => 'desc',
        'start' => now()->addDay(),
        'end' => now()->addDay()->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
        'presetPositions' => ['JAX_CTR'],
    ]);

    // Simulates legacy/drifted data: assigned to a position that was never
    // (or is no longer) on the event's own position list.
    EventPosition::create([
        'event_id' => $event->id,
        'user_id' => User::factory()->create()->id,
        'requested_position' => 'MCO_APP',
        'assigned_position' => 'MCO_APP',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    Livewire::test(EventPositionsManagement::class, ['event' => $event])
        ->set('positions', ['JAX_CTR'])
        ->call('save')
        ->assertDispatched('notify', type: 'error');

    expect($event->fresh()->presetPositions)->toBe(['JAX_CTR']);
});

test('removing an assigned position is blocked regardless of case', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff', 'events');
    $this->actingAs($staff);

    $event = Event::create([
        'title' => 'Test Event',
        'description' => 'desc',
        'start' => now()->addDay(),
        'end' => now()->addDay()->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
        'presetPositions' => ['JAX_CTR'],
    ]);

    EventPosition::create([
        'event_id' => $event->id,
        'user_id' => User::factory()->create()->id,
        'requested_position' => 'JAX_CTR',
        'assigned_position' => 'JAX_CTR',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    Livewire::test(EventPositionsManagement::class, ['event' => $event])
        ->call('removePosition', 'jax_ctr')
        ->assertDispatched('notify', type: 'error')
        ->assertSet('positions', ['JAX_CTR']);

    expect($event->fresh()->presetPositions)->toBe(['JAX_CTR']);
});

test('getAssignedPositions reflects who currently has a final position assigned', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff', 'events');
    $this->actingAs($staff);

    $event = Event::create([
        'title' => 'Test Event',
        'description' => 'desc',
        'start' => now()->addDay(),
        'end' => now()->addDay()->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
        'presetPositions' => ['JAX_CTR', 'MCO_APP'],
    ]);

    EventPosition::create([
        'event_id' => $event->id,
        'user_id' => User::factory()->create()->id,
        'requested_position' => 'JAX_CTR',
        'assigned_position' => 'JAX_CTR',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    Livewire::test(EventPositionsManagement::class, ['event' => $event])
        ->assertSet('assignedPositions', ['JAX_CTR']);
});
