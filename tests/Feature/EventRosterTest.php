<?php

use App\Enums\EventType;
use App\Models\Event;
use App\Models\EventPosition;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function rosterEvent(array $attributes = []): Event
{
    return Event::create(array_merge([
        'title' => 'Roster Test Event',
        'description' => 'desc',
        'start' => now()->addDay(),
        'end' => now()->addDay()->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
        'presetPositions' => ['JAX_CTR', 'MCO_APP'],
    ], $attributes));
}

test('an unpublished events roster is not shown', function () {
    $event = rosterEvent(['published' => false]);

    $this->get(route('events.show', ['event' => $event->id]))
        ->assertOk()
        ->assertSee('Positions not yet published')
        ->assertDontSee('JAX_CTR');
});

test('a guest sees filled or open badges but no names', function () {
    $event = rosterEvent(['published' => true]);

    EventPosition::create([
        'event_id' => $event->id,
        'user_id' => User::factory()->create(['first_name' => 'Jamie'])->id,
        'requested_position' => 'JAX_CTR',
        'assigned_position' => 'JAX_CTR',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    $response = $this->get(route('events.show', ['event' => $event->id]))->assertOk();

    $response->assertSee('JAX_CTR')
        ->assertSee('Assigned')
        ->assertSee('Open')
        ->assertDontSee('Jamie');
});

test('any logged in user sees assignee names on the roster', function () {
    $event = rosterEvent(['published' => true]);

    EventPosition::create([
        'event_id' => $event->id,
        'user_id' => User::factory()->create(['first_name' => 'Jamie', 'last_name' => 'Doe'])->id,
        'requested_position' => 'JAX_CTR',
        'assigned_position' => 'JAX_CTR',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    $viewer = User::factory()->create();
    $viewer->assignRole('core');

    $this->actingAs($viewer)
        ->get(route('events.show', ['event' => $event->id]))
        ->assertOk()
        ->assertSee('Jamie Doe');
});

test('an assignee working outside the event window has their actual times shown', function () {
    $event = rosterEvent(['published' => true]);

    EventPosition::create([
        'event_id' => $event->id,
        'user_id' => User::factory()->create(['first_name' => 'Early', 'last_name' => 'Bird'])->id,
        'requested_position' => 'JAX_CTR',
        'assigned_position' => 'JAX_CTR',
        'start' => $event->start,
        'end' => $event->end,
        'assigned_start' => $event->start->copy()->subHour(),
        'assigned_end' => $event->end->copy(),
    ]);

    $viewer = User::factory()->create();
    $viewer->assignRole('core');

    $this->actingAs($viewer)
        ->get(route('events.show', ['event' => $event->id]))
        ->assertOk()
        ->assertSee('Early Bird')
        ->assertSee($event->start->copy()->subHour()->format('H:i'));
});

test('an assignee working the exact event window has no extra times shown', function () {
    $event = rosterEvent(['published' => true]);

    EventPosition::create([
        'event_id' => $event->id,
        'user_id' => User::factory()->create(['first_name' => 'OnTime', 'last_name' => 'Controller'])->id,
        'requested_position' => 'JAX_CTR',
        'assigned_position' => 'JAX_CTR',
        'start' => $event->start,
        'end' => $event->end,
        'assigned_start' => $event->start,
        'assigned_end' => $event->end,
    ]);

    $viewer = User::factory()->create();
    $viewer->assignRole('core');

    $this->actingAs($viewer)
        ->get(route('events.show', ['event' => $event->id]))
        ->assertOk()
        ->assertSee('OnTime Controller')
        ->assertDontSee('('.$event->start->format('H:i'));
});

test('an assignment that has drifted off the position list still appears on the roster', function () {
    $event = rosterEvent(['published' => true, 'presetPositions' => ['JAX_CTR']]);

    EventPosition::create([
        'event_id' => $event->id,
        'user_id' => User::factory()->create(['first_name' => 'Drift', 'last_name' => 'Case'])->id,
        'requested_position' => 'MCO_APP',
        'assigned_position' => 'MCO_APP',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    $viewer = User::factory()->create();
    $viewer->assignRole('core');

    $this->actingAs($viewer)
        ->get(route('events.show', ['event' => $event->id]))
        ->assertOk()
        ->assertSee('MCO_APP')
        ->assertSee('Drift Case');
});
