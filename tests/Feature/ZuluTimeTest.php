<?php

use App\Enums\EventType;
use App\Livewire\EventsCalendar;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(PermissionSeeder::class));

function makeZuluTestEvent(): Event
{
    return Event::create([
        'title' => 'Zulu Event',
        'description' => 'desc',
        'start' => Carbon::create(2026, 4, 10, 18, 30, 0, 'UTC'),
        'end' => Carbon::create(2026, 4, 10, 21, 0, 0, 'UTC'),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
    ]);
}

test('the zulu time component renders a 24-hour UTC time with a Z suffix', function () {
    $this->blade('<x-zulu-time :time="$time"/>', ['time' => Carbon::create(2026, 4, 10, 18, 30, 0, 'UTC')])
        ->assertSee('Apr 10, 2026 18:30Z')
        ->assertSee('datetime="2026-04-10T18:30:00Z"', false);
});

test('the zulu time component converts non-UTC times to UTC', function () {
    $this->blade('<x-zulu-time :time="$time"/>', ['time' => Carbon::create(2026, 4, 10, 14, 30, 0, 'America/New_York')])
        ->assertSee('Apr 10, 2026 18:30Z');
});

test('the zulu time component accepts a custom format and strings', function () {
    $this->blade('<x-zulu-time time="2026-04-10 07:05:00" format="H:i"/>')
        ->assertSee('07:05Z')
        ->assertDontSee('Apr');
});

test('the zulu time component renders nothing for a null time', function () {
    $this->blade('<x-zulu-time :time="null"/>')
        ->assertDontSee('<time', false);
});

test('the event page shows start and end in 24-hour zulu time', function () {
    $event = makeZuluTestEvent();

    $this->actingAs(User::factory()->create())
        ->get(route('events.show', $event))
        ->assertOk()
        ->assertSee('Apr 10, 2026 18:30Z')
        ->assertSee('Apr 10, 2026 21:00Z')
        ->assertDontSee('6:30 PM');
});

test('the events calendar shows times in 24-hour zulu time', function () {
    makeZuluTestEvent();

    Livewire::test(EventsCalendar::class)
        ->set('currentYear', 2026)
        ->set('currentMonth', 4)
        ->assertSee('18:30Z')
        ->assertSee('21:00Z')
        ->assertDontSee('6:30 PM');
});
