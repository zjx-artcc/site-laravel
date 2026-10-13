<?php

use App\Enums\EventType;
use App\Livewire\EventRegistrants;
use App\Mail\EventPositionAssigned;
use App\Models\Event;
use App\Models\EventPosition;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function notificationEvent(array $attributes = []): Event
{
    return Event::create(array_merge([
        'title' => 'Notification Test Event',
        'description' => 'desc',
        'start' => now()->addDay(),
        'end' => now()->addDay()->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
        'presetPositions' => ['JAX_CTR', 'MCO_APP'],
    ], $attributes));
}

function notificationEventsStaff(): User
{
    $user = User::factory()->create();
    $user->assignRole('staff', 'events');

    return $user;
}

test('publishing emails only assigned rows, once per distinct user', function () {
    Mail::fake();

    $event = notificationEvent(['published' => false]);

    $assignedUser = User::factory()->create();
    $assigned = EventPosition::create([
        'event_id' => $event->id,
        'user_id' => $assignedUser->id,
        'requested_position' => 'JAX_CTR',
        'assigned_position' => 'JAX_CTR',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    $unassignedUser = User::factory()->create();
    EventPosition::create([
        'event_id' => $event->id,
        'user_id' => $unassignedUser->id,
        'requested_position' => 'MCO_APP',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    $this->actingAs(notificationEventsStaff());

    Livewire::test(EventRegistrants::class, ['event' => $event])
        ->call('publishPositions');

    Mail::assertQueuedCount(1);
    Mail::assertQueued(
        EventPositionAssigned::class,
        fn ($mail) => $mail->hasTo($assignedUser->email) && $mail->position->is($assigned) && ! $mail->isUpdate
    );
    Mail::assertNotQueued(
        EventPositionAssigned::class,
        fn ($mail) => $mail->hasTo($unassignedUser->email)
    );

    expect($assigned->fresh()->notified_at)->not->toBeNull();
});

test('publishing emails each distinct assigned user', function () {
    Mail::fake();

    $event = notificationEvent(['published' => false]);

    $userOne = User::factory()->create();
    EventPosition::create([
        'event_id' => $event->id,
        'user_id' => $userOne->id,
        'requested_position' => 'JAX_CTR',
        'assigned_position' => 'JAX_CTR',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    $userTwo = User::factory()->create();
    EventPosition::create([
        'event_id' => $event->id,
        'user_id' => $userTwo->id,
        'requested_position' => 'MCO_APP',
        'assigned_position' => 'MCO_APP',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    $this->actingAs(notificationEventsStaff());

    Livewire::test(EventRegistrants::class, ['event' => $event])
        ->call('publishPositions');

    Mail::assertQueuedCount(2);
    Mail::assertQueued(EventPositionAssigned::class, fn ($mail) => $mail->hasTo($userOne->email));
    Mail::assertQueued(EventPositionAssigned::class, fn ($mail) => $mail->hasTo($userTwo->email));
});

test('saving an assignment while unpublished sends nothing and leaves it armed for the next publish', function () {
    Mail::fake();

    $event = notificationEvent(['published' => false]);
    $registrantUser = User::factory()->create();

    $registrant = EventPosition::create([
        'event_id' => $event->id,
        'user_id' => $registrantUser->id,
        'requested_position' => 'JAX_CTR',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    $this->actingAs(notificationEventsStaff());

    Livewire::test(EventRegistrants::class, ['event' => $event])
        ->set("assignments.{$registrant->id}", [
            'assigned_start' => $event->start->utc()->format('Y-m-d\TH:i'),
            'assigned_end' => $event->end->utc()->format('Y-m-d\TH:i'),
            'assigned_position' => 'JAX_CTR',
        ])
        ->call('saveAll');

    Mail::assertNothingQueued();
    expect($registrant->fresh()->notified_at)->toBeNull();
});

test('saving an assignment while published sends exactly one email to that one user', function () {
    Mail::fake();

    $event = notificationEvent(['published' => true]);

    $userOne = User::factory()->create();
    $registrantOne = EventPosition::create([
        'event_id' => $event->id,
        'user_id' => $userOne->id,
        'requested_position' => 'JAX_CTR',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    $userTwo = User::factory()->create();
    EventPosition::create([
        'event_id' => $event->id,
        'user_id' => $userTwo->id,
        'requested_position' => 'MCO_APP',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    $this->actingAs(notificationEventsStaff());

    Livewire::test(EventRegistrants::class, ['event' => $event])
        ->set("assignments.{$registrantOne->id}", [
            'assigned_start' => $event->start->utc()->format('Y-m-d\TH:i'),
            'assigned_end' => $event->end->utc()->format('Y-m-d\TH:i'),
            'assigned_position' => 'JAX_CTR',
        ])
        ->call('saveAll');

    Mail::assertQueuedCount(1);
    Mail::assertQueued(
        EventPositionAssigned::class,
        fn ($mail) => $mail->hasTo($userOne->email) && $mail->isUpdate
    );
    Mail::assertNotQueued(EventPositionAssigned::class, fn ($mail) => $mail->hasTo($userTwo->email));

    expect($registrantOne->fresh()->notified_at)->not->toBeNull();
});

test('a later publish does not re-notify someone a prior save already notified', function () {
    Mail::fake();

    $event = notificationEvent(['published' => true]);

    $notifiedUser = User::factory()->create();
    $notified = EventPosition::create([
        'event_id' => $event->id,
        'user_id' => $notifiedUser->id,
        'requested_position' => 'JAX_CTR',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    $newUser = User::factory()->create();
    EventPosition::create([
        'event_id' => $event->id,
        'user_id' => $newUser->id,
        'requested_position' => 'MCO_APP',
        'assigned_position' => 'MCO_APP',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    $this->actingAs(notificationEventsStaff());

    // save() while already published notifies $notifiedUser immediately.
    Livewire::test(EventRegistrants::class, ['event' => $event])
        ->set("assignments.{$notified->id}", [
            'assigned_start' => $event->start->utc()->format('Y-m-d\TH:i'),
            'assigned_end' => $event->end->utc()->format('Y-m-d\TH:i'),
            'assigned_position' => 'JAX_CTR',
        ])
        ->call('saveAll');

    Mail::assertQueuedCount(1);

    // publishPositions() runs again later (e.g. for the newly-assigned registrant).
    Livewire::test(EventRegistrants::class, ['event' => $event])
        ->call('publishPositions');

    Mail::assertQueuedCount(2);
    Mail::assertNotQueued(EventPositionAssigned::class, fn ($mail) => $mail->hasTo($notifiedUser->email) && ! $mail->isUpdate);
    Mail::assertQueued(EventPositionAssigned::class, fn ($mail) => $mail->hasTo($newUser->email));
});

test('an unchanged re save while published sends nothing', function () {
    Mail::fake();

    $event = notificationEvent(['published' => true]);
    $registrantUser = User::factory()->create();

    $registrant = EventPosition::create([
        'event_id' => $event->id,
        'user_id' => $registrantUser->id,
        'requested_position' => 'JAX_CTR',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    $this->actingAs(notificationEventsStaff());

    $payload = [
        'assigned_start' => $event->start->utc()->format('Y-m-d\TH:i'),
        'assigned_end' => $event->end->utc()->format('Y-m-d\TH:i'),
        'assigned_position' => 'JAX_CTR',
    ];

    Livewire::test(EventRegistrants::class, ['event' => $event])
        ->set("assignments.{$registrant->id}", $payload)
        ->call('saveAll');

    Mail::assertQueuedCount(1);

    Livewire::test(EventRegistrants::class, ['event' => $event])
        ->set("assignments.{$registrant->id}", $payload)
        ->call('saveAll');

    Mail::assertQueuedCount(1);
});

test('the mailable renders without a real assigned time', function () {
    $event = notificationEvent(['published' => true]);

    $registrant = EventPosition::create([
        'event_id' => $event->id,
        'user_id' => User::factory()->create()->id,
        'requested_position' => 'JAX_CTR',
        'assigned_position' => 'JAX_CTR',
        'start' => $event->start,
        'end' => $event->end,
    ]);

    $html = (new EventPositionAssigned($registrant))->render();

    expect($html)
        ->toContain($event->title)
        ->toContain('JAX_CTR')
        ->toContain('TBD');
});
