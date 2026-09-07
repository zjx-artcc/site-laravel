<?php

use App\Enums\EventType;
use App\Livewire\EventsCalendar;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeEventManager(): User
{
    $user = User::factory()->create();
    $user->assignRole('staff', 'events');

    return $user;
}

test('updating an event persists the new title', function () {
    $this->actingAs(makeEventManager());

    $event = Event::create([
        'title' => 'Original Title',
        'description' => 'Original description',
        'start' => now()->addDay(),
        'end' => now()->addDay()->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
    ]);

    $response = $this->put(route('admin.events.update', ['event' => $event->id]), [
        'title' => 'Updated Title',
        'description' => 'Updated description',
        'start' => now()->addDay()->toDateTimeString(),
        'end' => now()->addDay()->addHours(2)->toDateTimeString(),
        'type' => EventType::HOME->value,
    ]);

    $response->assertRedirect(route('admin.events.index'));
    expect($event->fresh()->title)->toBe('Updated Title');
});

test('a script tag in the description is stripped on create', function () {
    $this->actingAs(makeEventManager());

    $response = $this->post(route('admin.events.store'), [
        'title' => 'XSS Test Event',
        'description' => '<p>Hello</p><script>alert(1)</script><img src=x onerror=alert(2)>',
        'start' => now()->addDay()->toDateTimeString(),
        'end' => now()->addDay()->addHours(2)->toDateTimeString(),
        'type' => EventType::HOME->value,
    ]);

    $response->assertRedirect(route('admin.events.index'));

    $event = Event::where('title', 'XSS Test Event')->firstOrFail();

    expect($event->description)
        ->toContain('Hello')
        ->not->toContain('<script')
        ->not->toContain('onerror');
});

test('a script tag in the description is stripped on update', function () {
    $this->actingAs(makeEventManager());

    $event = Event::create([
        'title' => 'Original Title',
        'description' => 'Original description',
        'start' => now()->addDay(),
        'end' => now()->addDay()->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
    ]);

    $this->put(route('admin.events.update', ['event' => $event->id]), [
        'title' => $event->title,
        'description' => '<script>alert(document.cookie)</script><p>Safe text</p>',
        'start' => now()->addDay()->toDateTimeString(),
        'end' => now()->addDay()->addHours(2)->toDateTimeString(),
        'type' => EventType::HOME->value,
    ]);

    expect($event->fresh()->description)
        ->toContain('Safe text')
        ->not->toContain('<script');
});

test('the public event page never renders an unsanitized script tag even with legacy raw HTML', function () {
    // Simulates data that bypassed the controller (e.g. seeded/imported directly),
    // so the display-side purification in events/show.blade.php is what's under test.
    $event = Event::create([
        'title' => 'Legacy Data Event',
        'description' => '<script>alert(1)</script><p>Legit content</p>',
        'start' => now()->addDay(),
        'end' => now()->addDay()->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
    ]);

    $this->get(route('events.show', ['event' => $event->id]))
        ->assertOk()
        ->assertSee('Legit content')
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('a non-numeric public event URL returns 404', function () {
    $this->get('/events/cmmu2pltw0037qq7t2jiy3bk')
        ->assertNotFound();
});

test('admin events list renders event titles', function () {
    $this->actingAs(makeEventManager());

    Event::create([
        'title' => 'Visible In Admin List',
        'description' => 'desc',
        'start' => now()->addDay(),
        'end' => now()->addDay()->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
    ]);

    $this->get(route('admin.events.index'))
        ->assertOk()
        ->assertSee('Visible In Admin List');
});

test('public calendar hides hidden events', function () {
    $mid = Carbon::create(now()->year, now()->month, 15, 12);

    Event::create([
        'title' => 'Published Event',
        'description' => 'desc',
        'start' => $mid,
        'end' => $mid->copy()->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
    ]);

    Event::create([
        'title' => 'Secret Event',
        'description' => 'desc',
        'start' => $mid,
        'end' => $mid->copy()->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => true,
    ]);

    Livewire::test(EventsCalendar::class)
        ->assertSee('Published Event')
        ->assertDontSee('Secret Event');
});

test('calendar shows events on visible adjacent-month days', function () {
    // April 2026 grid begins on Sun Mar 29, so it renders trailing March days.
    $gridStart = Carbon::create(2026, 4, 1)->startOfWeek(Carbon::SUNDAY);
    expect($gridStart->month)->toBe(3); // sanity: it really is an adjacent-month day

    Event::create([
        'title' => 'Adjacent Month Event',
        'description' => 'desc',
        'start' => $gridStart->copy()->addHours(12),
        'end' => $gridStart->copy()->addHours(14),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
    ]);

    Livewire::test(EventsCalendar::class)
        ->set('currentYear', 2026)
        ->set('currentMonth', 4)
        ->assertSee('Adjacent Month Event');
});
