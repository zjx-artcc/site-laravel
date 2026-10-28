<?php

use App\Enums\TrainingStatus;
use App\Enums\TrainingType;
use App\Jobs\SyncTrainingTickets;
use App\Models\TrainingAssignment;
use App\Models\TrainingTicket;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => $this->seed(PermissionSeeder::class));

function makeNoShowTestTicket(User $student, User $instructor, int $location): TrainingTicket
{
    return TrainingTicket::create([
        'user_id' => $student->id,
        'instructor_id' => $instructor->id,
        'position' => 'JAX_GND',
        'session_start' => now(),
        'session_end' => now()->addHour(),
        'movements' => 0,
        'score' => 1,
        'notes' => 'Student did not attend.',
        'location' => $location,
        'vatusa_synced' => true,
    ]);
}

test('the create form offers No Show as a session location', function () {
    $instructor = User::factory()->create();
    $instructor->assignRole(['staff', 'training']);

    $this->actingAs($instructor)
        ->get(route('training-tickets.create'))
        ->assertOk()
        ->assertSee('<option value="3"', false)
        ->assertSee('No Show');
});

test('a no-show ticket can be stored', function () {
    Http::fake();
    Mail::fake();

    $instructor = User::factory()->create();
    $instructor->assignRole(['staff', 'training']);
    $student = User::factory()->create();

    $this->actingAs($instructor)
        ->post(route('training-tickets.store'), [
            'student' => $student->id,
            'position' => 'JAX_GND',
            'location' => TrainingTicket::LOCATION_NO_SHOW,
            'sessionStart' => now()->format('Y-m-d H:i:s'),
            'sessionEnd' => now()->addHour()->format('Y-m-d H:i:s'),
            'movements' => 0,
            'score' => 1,
            'notes' => '<p>Student did not attend.</p>',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $ticket = TrainingTicket::where('user_id', $student->id)->firstOrFail();

    expect($ticket->isNoShow())->toBeTrue()
        ->and($ticket->locationLabel())->toBe('No Show');
});

test('locations above No Show are rejected', function () {
    $instructor = User::factory()->create();
    $instructor->assignRole(['staff', 'training']);
    $student = User::factory()->create();

    $this->actingAs($instructor)
        ->post(route('training-tickets.store'), [
            'student' => $student->id,
            'position' => 'JAX_GND',
            'location' => 4,
            'sessionStart' => now()->format('Y-m-d H:i:s'),
            'sessionEnd' => now()->addHour()->format('Y-m-d H:i:s'),
            'movements' => 0,
            'score' => 1,
            'notes' => '<p>Notes</p>',
        ])
        ->assertSessionHasErrors('location');
});

test('no-show tickets sync to VATUSA as Classroom with a No Show note', function () {
    Http::fake(['*' => Http::response(['data' => ['id' => 123]])]);

    $instructor = User::factory()->create();
    $student = User::factory()->create();
    $ticket = makeNoShowTestTicket($student, $instructor, TrainingTicket::LOCATION_NO_SHOW);
    $ticket->update(['vatusa_synced' => false]);

    (new SyncTrainingTickets)->handle();

    Http::assertSent(fn (Request $request) => $request['location'] === 0
        && str_starts_with($request['notes'], 'No Show'));

    expect($ticket->fresh()->vatusa_synced)->toBeTruthy();
});

test('regular tickets sync their location unchanged', function () {
    Http::fake(['*' => Http::response(['data' => ['id' => 123]])]);

    $instructor = User::factory()->create();
    $student = User::factory()->create();
    $ticket = makeNoShowTestTicket($student, $instructor, 2);
    $ticket->update(['vatusa_synced' => false]);

    (new SyncTrainingTickets)->handle();

    Http::assertSent(fn (Request $request) => $request['location'] === 2
        && ! str_starts_with($request['notes'], 'No Show'));
});

test('training staff see the student no-show count on a ticket, the student does not', function () {
    $staff = User::factory()->create();
    $staff->assignRole('training');
    $student = User::factory()->create(['rostered' => true]);

    $ticket = makeNoShowTestTicket($student, $staff, TrainingTicket::LOCATION_NO_SHOW);
    makeNoShowTestTicket($student, $staff, TrainingTicket::LOCATION_NO_SHOW);
    makeNoShowTestTicket($student, $staff, 1);

    $this->actingAs($staff)
        ->get(route('training-tickets.show', $ticket))
        ->assertOk()
        ->assertSee('Student No Shows (staff only)')
        ->assertSee('value="2"', false);

    $this->actingAs($student)
        ->get(route('training-tickets.show', $ticket))
        ->assertOk()
        ->assertDontSee('Student No Shows');
});

test('training staff see the no-show count on a student ticket list, the student does not', function () {
    $staff = User::factory()->create();
    $staff->assignRole('training');
    $student = User::factory()->create(['rostered' => true]);

    makeNoShowTestTicket($student, $staff, TrainingTicket::LOCATION_NO_SHOW);

    $this->actingAs($staff)
        ->get(route('users.show.training-tickets', $student->id))
        ->assertOk()
        ->assertSee('No Shows:');

    $this->actingAs($student)
        ->get(route('users.show.training-tickets', $student->id))
        ->assertOk()
        ->assertDontSee('No Shows:');
});

test('the training assignments table shows each student no-show count', function () {
    $staff = User::factory()->create();
    $staff->assignRole(['staff', 'training']);
    $student = User::factory()->create(['rostered' => true]);

    TrainingAssignment::create([
        'user_id' => $student->id,
        'active' => true,
        'training_type' => TrainingType::S1->value,
        'status' => TrainingStatus::ACTIVE->value,
    ]);

    makeNoShowTestTicket($student, $staff, TrainingTicket::LOCATION_NO_SHOW);
    makeNoShowTestTicket($student, $staff, TrainingTicket::LOCATION_NO_SHOW);
    makeNoShowTestTicket($student, $staff, 1);

    $response = $this->actingAs($staff)
        ->get(route('training-assignments.index'))
        ->assertOk()
        ->assertSee('No Shows');

    expect($response->viewData('trainingAssignments')->first()->student->no_show_tickets_count)->toBe(2);
});
