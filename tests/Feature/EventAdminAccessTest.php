<?php

use App\Enums\EventType;
use App\Livewire\EventRegistrants;
use App\Models\Event;
use App\Models\EventPosition;
use App\Models\FeaturedField;
use App\Models\News;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeEvent(array $attributes = []): Event
{
    return Event::create(array_merge([
        'title' => 'Test Event',
        'description' => 'desc',
        'start' => now()->addDay(),
        'end' => now()->addDay()->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
    ], $attributes));
}

function makeEventsStaff(): User
{
    $user = User::factory()->create();
    $user->assignRole('staff', 'events');

    return $user;
}

test('guests cannot read another users event registrations', function () {
    $target = User::factory()->create();

    $this->get(route('users.show.registered-events', $target))
        ->assertRedirect(route('login'));
});

test('a user cannot read another users event registrations', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('core');

    $this->actingAs($viewer)
        ->get(route('users.show.registered-events', User::factory()->create()))
        ->assertForbidden();
});

test('a user can read their own event registrations', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('users.show.registered-events', $user))
        ->assertOk();
});

test('events staff can read another users event registrations', function () {
    $this->actingAs(makeEventsStaff())
        ->get(route('users.show.registered-events', User::factory()->create()))
        ->assertOk();
});

test('news management is limited to admins', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $this->actingAs($staff)->get(route('admin.news.index'))->assertForbidden();
    $this->actingAs($staff)->post(route('admin.news.store'), [
        'title' => 'Sneaky',
        'content' => 'Should not publish',
    ])->assertForbidden();

    $admin = User::factory()->create();
    $admin->assignRole('staff', 'admin');

    $this->actingAs($admin)->get(route('admin.news.index'))->assertOk();
});

test('the event field preset page lists and accepts new fields', function () {
    $this->actingAs(makeEventsStaff());

    FeaturedField::create(['name' => 'KJAX']);

    $this->get(route('admin.events.event-fields.index'))
        ->assertOk()
        ->assertSee('KJAX');

    $this->post(route('admin.events.event-fields.store'), ['name' => 'kmco'])
        ->assertRedirect(route('admin.events.event-fields.index'));

    expect(FeaturedField::where('name', 'KMCO')->exists())->toBeTrue();
});

test('the edit form keeps the events existing start time', function () {
    $this->actingAs(makeEventsStaff());

    $event = makeEvent(['start' => now()->addDay()->setTime(18, 30)]);

    $this->get(route('admin.events.edit', ['event' => $event->id]))
        ->assertOk()
        ->assertSee($event->start->format('Y-m-d\TH:i'));
});

test('event banners use public-disk URLs and retain access to legacy stored paths', function () {
    Storage::fake('public');

    $event = makeEvent(['event_image_route' => 'storage/events/legacy-banner.png']);

    expect($event->banner_url)->toBe(Storage::disk('public')->url('events/legacy-banner.png'));

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertSee($event->banner_url, false);
});

test('new event banners store a public-disk path instead of a hand-built URL', function () {
    Storage::fake('public');
    $this->actingAs(makeEventsStaff());

    $this->post(route('admin.events.store'), [
        'title' => 'Banner Event',
        'description' => 'Event with a banner.',
        'start' => now()->addDay()->format('Y-m-d H:i:s'),
        'end' => now()->addDay()->addHours(2)->format('Y-m-d H:i:s'),
        'type' => EventType::HOME->value,
        'image' => UploadedFile::fake()->image('banner.png', 1200, 400),
    ])->assertRedirect(route('admin.events.index'));

    $event = Event::where('title', 'Banner Event')->sole();

    expect($event->event_image_route)->toBe("event/event_{$event->id}.png")
        ->and($event->banner_url)->toBe(Storage::disk('public')->url($event->event_image_route));
    Storage::disk('public')->assertExists($event->event_image_route);
});

test('an injected assignment key for another event is never processed by saveAll', function () {
    $this->actingAs(makeEventsStaff());

    $shownEvent = makeEvent(['title' => 'Shown Event']);
    $otherEvent = makeEvent(['title' => 'Other Event']);

    $foreign = EventPosition::create([
        'event_id' => $otherEvent->id,
        'user_id' => User::factory()->create()->id,
        'requested_position' => 'JAX_CTR',
        'start' => $otherEvent->start,
        'end' => $otherEvent->end,
    ]);

    // saveAll() only loops over $this->registrants (the mounted event's own
    // rows), so an extra key for another event's registrant — however it got
    // into component state — is simply never iterated, let alone persisted.
    Livewire::test(EventRegistrants::class, ['event' => $shownEvent])
        ->set("assignments.{$foreign->id}", [
            'assigned_start' => $otherEvent->start->format('Y-m-d\TH:i'),
            'assigned_end' => $otherEvent->end->format('Y-m-d\TH:i'),
            'assigned_position' => 'JAX_CTR',
        ])
        ->call('saveAll');

    expect($foreign->fresh()->assigned_position)->toBeNull();
});

test('the pages touched by the events work all render', function () {
    $staff = makeEventsStaff();
    $staff->assignRole('admin');
    $this->actingAs($staff);

    FeaturedField::create(['name' => 'KJAX']);
    News::create(['title' => 'Smoke News', 'content' => 'hello', 'published_at' => now()]);

    $event = makeEvent(['featured_fields' => ['KJAX']]);

    EventPosition::create([
        'event_id' => $event->id,
        'user_id' => $staff->id,
        'requested_position' => 'JAX_CTR',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    $this->get(route('home'))->assertOk();
    $this->get(route('events.index'))->assertOk();
    $this->get(route('events.show', ['event' => $event->id]))->assertOk();
    $this->get(route('admin.events.index'))->assertOk();
    $this->get(route('admin.events.manage', ['event' => $event->id]))->assertOk();
    $this->get(route('admin.events.edit', ['event' => $event->id]))->assertOk();
    $this->get(route('admin.events.positions', ['event' => $event->id]))->assertOk();
    $this->get(route('admin.events.create'))->assertOk();
    $this->get(route('admin.events.event-fields.index'))->assertOk();
    $this->get(route('admin.events.position-presets.index'))->assertOk();
    $this->get(route('admin.news.index'))->assertOk();
    $this->get(route('admin.news.create'))->assertOk();
    $this->get(route('users.show.registered-events', $staff))->assertOk();
});

test('a hidden event is not reachable by direct link', function () {
    $event = makeEvent(['hidden' => true]);

    $this->get(route('events.show', ['event' => $event->id]))->assertNotFound();

    $viewer = User::factory()->create();
    $viewer->assignRole('core');
    $this->actingAs($viewer)
        ->get(route('events.show', ['event' => $event->id]))
        ->assertNotFound();

    $this->actingAs(makeEventsStaff())
        ->get(route('events.show', ['event' => $event->id]))
        ->assertOk();
});

test('the registrant roster lives on the positions tab, not the overview tab', function () {
    $this->actingAs(makeEventsStaff());

    $event = makeEvent();

    EventPosition::create([
        'event_id' => $event->id,
        'user_id' => User::factory()->create()->id,
        'requested_position' => 'JAX_CTR',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    $this->get(route('admin.events.manage', ['event' => $event->id]))
        ->assertOk()
        ->assertDontSee('Registrants');

    $this->get(route('admin.events.positions', ['event' => $event->id]))
        ->assertOk()
        ->assertSee('Registrants')
        ->assertSee('Positions Locked');
});

test('an event field in use cannot be deleted', function () {
    $this->actingAs(makeEventsStaff());

    $field = FeaturedField::create(['name' => 'KJAX']);
    makeEvent(['featured_fields' => ['KJAX']]);

    $this->delete(route('admin.events.event-fields.destroy', ['eventField' => $field->id]))
        ->assertSessionHas('error');

    expect(FeaturedField::whereKey($field->id)->exists())->toBeTrue();

    $unused = FeaturedField::create(['name' => 'KDAB']);

    $this->delete(route('admin.events.event-fields.destroy', ['eventField' => $unused->id]))
        ->assertRedirect(route('admin.events.event-fields.index'));

    expect(FeaturedField::whereKey($unused->id)->exists())->toBeFalse();
});

test('publishing positions requires the publish events permission', function () {
    $event = makeEvent();

    $assigner = User::factory()->create();
    $assigner->assignRole('staff');
    $assigner->givePermissionTo('assign event positions');

    $this->actingAs($assigner);

    Livewire::test(EventRegistrants::class, ['event' => $event])
        ->call('publishPositions')
        ->assertForbidden();

    expect($event->fresh()->published)->toBeFalse();

    $this->actingAs(makeEventsStaff());

    Livewire::test(EventRegistrants::class, ['event' => $event])
        ->call('publishPositions');

    expect($event->fresh()->published)->toBeTrue();
});

test('the assignment form is seeded from the saved assignment', function () {
    $this->actingAs(makeEventsStaff());

    $event = makeEvent();

    $registrant = EventPosition::create([
        'event_id' => $event->id,
        'user_id' => User::factory()->create()->id,
        'requested_position' => 'JAX_CTR',
        'start' => $event->start,
        'end' => $event->end,
        'assigned_start' => $event->start->copy()->addHour(),
        'assigned_end' => $event->end->copy()->addHour(),
        'assigned_position' => 'MCO_APP',
    ]);

    Livewire::test(EventRegistrants::class, ['event' => $event])
        ->assertSet(
            "assignments.{$registrant->id}.assigned_start",
            $event->start->copy()->addHour()->utc()->format('Y-m-d\TH:i')
        );
});

test('the positions tab is hidden entirely from a user without the manage events permission', function () {
    $bareStaff = User::factory()->create();
    $bareStaff->assignRole('staff');
    $this->actingAs($bareStaff);

    $event = makeEvent();

    $this->get(route('admin.events.positions', ['event' => $event->id]))->assertForbidden();
});

test('a manage events-only user sees the positions tab as read-only', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('staff');
    $viewer->givePermissionTo('manage events');
    $this->actingAs($viewer);

    $event = makeEvent();

    $this->get(route('admin.events.positions', ['event' => $event->id]))
        ->assertOk()
        ->assertSee('read-only');

    $this->get(route('admin.events.manage', ['event' => $event->id]))
        ->assertOk()
        ->assertSee('Positions');
});

test('a manage events-only user cannot assign a final position from the roster', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('staff');
    $viewer->givePermissionTo('manage events');
    $this->actingAs($viewer);

    $event = makeEvent();

    $registrant = EventPosition::create([
        'event_id' => $event->id,
        'user_id' => User::factory()->create()->id,
        'requested_position' => 'JAX_CTR',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    Livewire::test(EventRegistrants::class, ['event' => $event])
        ->set("assignments.{$registrant->id}.assigned_position", 'JAX_CTR')
        ->call('saveAll')
        ->assertForbidden();

    expect($registrant->fresh()->assigned_position)->toBeNull();
});
