<?php

use App\Models\TrainingTicket;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => $this->seed(PermissionSeeder::class));

test('the training ticket form provides a markdown editor for instructor notes', function () {
    $instructor = User::factory()->create();
    $instructor->assignRole(['staff', 'training']);

    $this->actingAs($instructor)
        ->get(route('training-tickets.create'))
        ->assertOk()
        ->assertSee('markdown-editor-instructor_notes')
        ->assertSee('Markdown shortcuts are supported.');
});

test('formatted instructor notes are sanitized and rendered for training staff', function () {
    Http::fake();
    Mail::fake();

    $instructor = User::factory()->create();
    $instructor->assignRole(['staff', 'training']);
    $student = User::factory()->create();

    $this->actingAs($instructor)
        ->post(route('training-tickets.store'), [
            'student' => $student->id,
            'position' => 'MCO_APP',
            'location' => 1,
            'sessionStart' => now()->format('Y-m-d H:i:s'),
            'sessionEnd' => now()->addHour()->format('Y-m-d H:i:s'),
            'movements' => 10,
            'score' => 5,
            'notes' => '<p><em>Session feedback</em></p><script>alert("xss")</script>',
            'instructor_notes' => '<p><strong>Private feedback</strong></p><script>alert("xss")</script>',
        ])
        ->assertRedirect();

    $ticket = TrainingTicket::where('user_id', $student->id)->firstOrFail();

    expect($ticket->instructor_notes)
        ->toBe('<p><strong>Private feedback</strong></p>');
    expect($ticket->notes)
        ->toBe('<p><em>Session feedback</em></p>');

    $this->actingAs($instructor)
        ->get(route('training-tickets.show', $ticket))
        ->assertOk()
        ->assertSee('<em>Session feedback</em>', false)
        ->assertSee('<strong>Private feedback</strong>', false)
        ->assertDontSee('alert("xss")');
});

test('instructor notes are hidden from the student but shown to training staff', function () {
    $staff = User::factory()->create();
    $staff->assignRole('training');
    $student = User::factory()->create(['rostered' => true]);

    $ticket = TrainingTicket::create([
        'user_id' => $student->id,
        'instructor_id' => $staff->id,
        'position' => 'MCO_APP',
        'session_start' => now(),
        'session_end' => now()->addHour(),
        'movements' => 10,
        'score' => 5,
        'notes' => 'Session notes.',
        'instructor_notes' => 'SECRET-INSTRUCTOR-NOTE',
        'location' => 1,
    ]);

    $this->actingAs($student)
        ->get(route('training-tickets.show', $ticket))
        ->assertOk()
        ->assertDontSee('SECRET-INSTRUCTOR-NOTE');

    $this->actingAs($staff)
        ->get(route('training-tickets.show', $ticket))
        ->assertOk()
        ->assertSee('SECRET-INSTRUCTOR-NOTE');
});

test('a training staff member viewing their own ticket does not see instructor notes', function () {
    $instructor = User::factory()->create();
    $student = User::factory()->create(['rostered' => true]);
    $student->assignRole('training'); // student is also a trainer

    $ticket = TrainingTicket::create([
        'user_id' => $student->id,
        'instructor_id' => $instructor->id,
        'position' => 'MCO_APP',
        'session_start' => now(),
        'session_end' => now()->addHour(),
        'movements' => 10,
        'score' => 5,
        'notes' => 'Session notes.',
        'instructor_notes' => 'SECRET-INSTRUCTOR-NOTE',
        'location' => 1,
    ]);

    $this->actingAs($student)
        ->get(route('training-tickets.show', $ticket))
        ->assertOk()
        ->assertDontSee('SECRET-INSTRUCTOR-NOTE')
        ->assertDontSee('Admin Actions');
});
