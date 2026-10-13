<?php

use App\Enums\EventType;
use App\Models\Event;

function startingSoonEvent(array $attributes = []): Event
{
    return Event::create(array_merge([
        'title' => 'Starting Soon Test Event',
        'description' => 'desc',
        'start' => now()->addHours(2),
        'end' => now()->addHours(4),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
    ], $attributes));
}

test('an event starting within 24 hours is starting soon', function () {
    expect(startingSoonEvent(['start' => now()->addHours(2)])->isStartingSoon())->toBeTrue();
});

test('an event starting more than 24 hours out is not starting soon', function () {
    expect(startingSoonEvent(['start' => now()->addHours(30)])->isStartingSoon())->toBeFalse();
});

test('an event already in progress is not starting soon', function () {
    expect(startingSoonEvent(['start' => now()->subHour()])->isStartingSoon())->toBeFalse();
});

test('the starting soon badge appears on the homepage for a qualifying event', function () {
    startingSoonEvent(['title' => 'Imminent Event', 'start' => now()->addHours(2)]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Imminent Event')
        ->assertSee('Starting Soon');
});
