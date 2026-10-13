<?php

use App\Jobs\SyncTrainingTickets;
use App\Mail\TrainingTicketCreated;
use App\Models\CertificationFacility;
use App\Models\CertificationLevel;
use App\Models\TrainingTicket;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => $this->seed(PermissionSeeder::class));

function ticketPayload(User $student, ?int $levelId = null): array
{
    return array_filter([
        'student' => $student->id,
        'position' => 'MCO_APP',
        'location' => 1,
        'sessionStart' => now()->format('Y-m-d H:i:s'),
        'sessionEnd' => now()->addHour()->format('Y-m-d H:i:s'),
        'movements' => 10,
        'score' => 5,
        'notes' => 'Great work.',
        'certification_level_id' => $levelId,
    ], fn ($value) => ! is_null($value));
}

test('an instructor with certifications:write issues a cert via a training ticket', function () {
    Http::fake();
    Mail::fake();

    $facility = CertificationFacility::factory()->create(['identifier' => 'ZJX']);
    $level = CertificationLevel::factory()->create(['facility_id' => $facility->id, 'name' => 'Ground', 'abbreviation' => 'GND', 'level' => 1]);

    $instructor = User::factory()->create();
    // staff => 'view dashboard' (admin group), training => route access, instructor => certifications:write
    $instructor->assignRole(['staff', 'training', 'instructor']);
    $student = User::factory()->create();

    $this->actingAs($instructor);
    $this->post(route('training-tickets.store'), ticketPayload($student, $level->id))
        ->assertRedirect();

    $this->assertDatabaseHas('user_certifications', ['user_id' => $student->id, 'certification_level_id' => $level->id]);

    $ticket = TrainingTicket::where('user_id', $student->id)->firstOrFail();
    expect($ticket->issued_certification_level_id)->toBe($level->id);
    expect($ticket->certificationIssuedLabel())->toBe('Certification Issued: ZJX Ground (GND)');
});

test('training staff without certifications:write cannot issue a cert', function () {
    Http::fake();
    Mail::fake();

    $level = CertificationLevel::factory()->create();

    $instructor = User::factory()->create();
    $instructor->assignRole(['staff', 'training']); // route access, but no certifications:write
    $student = User::factory()->create();

    $this->actingAs($instructor);
    $this->post(route('training-tickets.store'), ticketPayload($student, $level->id))
        ->assertRedirect();

    $this->assertDatabaseMissing('user_certifications', ['user_id' => $student->id, 'certification_level_id' => $level->id]);

    $ticket = TrainingTicket::where('user_id', $student->id)->firstOrFail();
    expect($ticket->issued_certification_level_id)->toBeNull();
});

test('a training ticket with empty quill notes is rejected', function () {
    Http::fake();
    Mail::fake();

    $instructor = User::factory()->create();
    $instructor->assignRole(['staff', 'training']);
    $student = User::factory()->create();

    $this->actingAs($instructor);
    $payload = ticketPayload($student);
    $payload['notes'] = '<p><br></p>'; // Quill's "empty" markup

    $this->post(route('training-tickets.store'), $payload)
        ->assertSessionHasErrors('notes');

    $this->assertDatabaseMissing('training_tickets', ['user_id' => $student->id]);
});

test('a training ticket accepts session and instructor notes longer than the previous limit', function () {
    Http::fake();
    Mail::fake();

    $instructor = User::factory()->create();
    $instructor->assignRole(['staff', 'training']);
    $student = User::factory()->create();
    $sessionNotes = str_repeat('Session note ', 500).'Session note';
    $instructorNotes = str_repeat('Instructor note ', 500).'Instructor note';

    $this->actingAs($instructor)
        ->post(route('training-tickets.store'), [
            ...ticketPayload($student),
            'notes' => $sessionNotes,
            'instructor_notes' => $instructorNotes,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('training_tickets', [
        'user_id' => $student->id,
        'notes' => $sessionNotes,
        'instructor_notes' => $instructorNotes,
    ]);
});

test('the vatusa sync appends the certification issued line to the notes', function () {
    $facility = CertificationFacility::factory()->create(['identifier' => 'ZJX']);
    $level = CertificationLevel::factory()->create(['facility_id' => $facility->id, 'name' => 'Ground', 'abbreviation' => 'GND']);
    $student = User::factory()->create();
    $instructor = User::factory()->create();

    Http::fake([
        config('app.vatusa_api_url').'/v2/user/'.$student->id.'/training/record' => Http::response(['data' => ['id' => '123']], 200),
    ]);

    TrainingTicket::create([
        'user_id' => $student->id,
        'instructor_id' => $instructor->id,
        'position' => 'MCO_APP',
        'session_start' => now()->format('Y-m-d H:i:s'),
        'session_end' => now()->addHour()->format('Y-m-d H:i:s'),
        'movements' => 10,
        'score' => 5,
        'notes' => 'Base notes.',
        'location' => 1,
        'issued_certification_level_id' => $level->id,
    ]);

    (new SyncTrainingTickets)->handle();

    Http::assertSent(function ($request) {
        return str_contains($request['notes'] ?? '', 'Base notes.')
            && str_contains($request['notes'] ?? '', 'Certification Issued: ZJX Ground (GND)');
    });
});

test('the vatusa sync leaves notes unchanged when no cert was issued', function () {
    $student = User::factory()->create();
    $instructor = User::factory()->create();

    Http::fake([
        config('app.vatusa_api_url').'/v2/user/'.$student->id.'/training/record' => Http::response(['data' => ['id' => '123']], 200),
    ]);

    TrainingTicket::create([
        'user_id' => $student->id,
        'instructor_id' => $instructor->id,
        'position' => 'MCO_APP',
        'session_start' => now()->format('Y-m-d H:i:s'),
        'session_end' => now()->addHour()->format('Y-m-d H:i:s'),
        'movements' => 10,
        'score' => 5,
        'notes' => 'Base notes.',
        'location' => 1,
    ]);

    (new SyncTrainingTickets)->handle();

    Http::assertSent(fn ($request) => ($request['notes'] ?? null) === 'Base notes.');
});

test('the training ticket show page displays the pushed certification', function () {
    $facility = CertificationFacility::factory()->create(['identifier' => 'ZJX']);
    $level = CertificationLevel::factory()->create(['facility_id' => $facility->id, 'name' => 'Ground', 'abbreviation' => 'GND']);
    $student = User::factory()->create();
    $instructor = User::factory()->create();
    $instructor->assignRole(['staff', 'training']);

    $ticket = TrainingTicket::create([
        'user_id' => $student->id,
        'instructor_id' => $instructor->id,
        'position' => 'MCO_APP',
        'session_start' => now()->format('Y-m-d H:i:s'),
        'session_end' => now()->addHour()->format('Y-m-d H:i:s'),
        'movements' => 10,
        'score' => 5,
        'notes' => 'Base notes.',
        'location' => 1,
        'issued_certification_level_id' => $level->id,
    ]);

    $this->actingAs($instructor);
    $this->get(route('training-tickets.show', $ticket))
        ->assertStatus(200)
        ->assertSee('Certification Pushed')
        ->assertSee('GND');
});

test('the training ticket email includes the certification issued line', function () {
    $facility = CertificationFacility::factory()->create(['identifier' => 'ZJX']);
    $level = CertificationLevel::factory()->create(['facility_id' => $facility->id, 'name' => 'Ground', 'abbreviation' => 'GND']);
    $student = User::factory()->create();
    $instructor = User::factory()->create();

    $ticket = TrainingTicket::create([
        'user_id' => $student->id,
        'instructor_id' => $instructor->id,
        'position' => 'MCO_APP',
        'session_start' => now()->format('Y-m-d H:i:s'),
        'session_end' => now()->addHour()->format('Y-m-d H:i:s'),
        'movements' => 10,
        'score' => 5,
        'notes' => 'Base notes.',
        'location' => 1,
        'issued_certification_level_id' => $level->id,
    ]);

    $mailable = new TrainingTicketCreated($ticket);

    $mailable->assertSeeInHtml('Certification Issued: ZJX Ground (GND)');
});
