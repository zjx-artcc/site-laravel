<?php

use App\Jobs\RemoveUserFromRoster;
use App\Mail\ControllerRemovedFromRoster;
use App\Mail\RosterRemovalFailed;
use App\Models\ControllerMonthlyStat;
use App\Models\TrainingTicket;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function makeTicket(int $studentId, int $instructorId, string $start, string $end): void
{
    TrainingTicket::withoutSyncingToSearch(function () use ($studentId, $instructorId, $start, $end) {
        TrainingTicket::create([
            'user_id' => $studentId,
            'instructor_id' => $instructorId,
            'session_start' => $start,
            'session_end' => $end,
            'position' => 'JAX_APP',
            'movements' => 0,
            'score' => 1,
            'notes' => 'Test session',
            'location' => 0,
        ]);
    });
}

test('quarterly rows include training hours split into student and instructor time', function () {
    $controller = User::factory()->create(['rostered' => true, 'rating' => 5, 'facility' => config('app.vatusa_facility')]);
    $instructor = User::factory()->create(['rostered' => true, 'rating' => 8, 'facility' => config('app.vatusa_facility')]);

    // Puts both users into the "logged history" table for Q3 2026.
    foreach ([$controller, $instructor] as $user) {
        ControllerMonthlyStat::create([
            'user_id' => $user->id, 'year' => 2026, 'month' => 7,
            'delivery_hours' => 0, 'ground_hours' => 0, 'tower_hours' => 0,
            'approach_hours' => 0, 'center_hours' => 0,
        ]);
    }

    // 2h with controller as student, instructor teaching.
    makeTicket($controller->id, $instructor->id, '2026-07-05 10:00:00', '2026-07-05 12:00:00');
    // 1h with controller as student again.
    makeTicket($controller->id, $instructor->id, '2026-07-06 10:00:00', '2026-07-06 11:00:00');

    $admin = User::factory()->create();
    $admin->assignRole('admin', 'staff');

    $response = $this->actingAs($admin)
        ->get(route('statistics.quarterly', ['year' => 2026, 'quarter' => 3]))
        ->assertOk();

    $rows = $response->viewData('rows');

    $studentRow = $rows->getCollection()->firstWhere('user.id', $controller->id);
    $instructorRow = $rows->getCollection()->firstWhere('user.id', $instructor->id);

    expect($studentRow->training_student)->toBe(3.0)
        ->and($studentRow->training_instructor)->toBe(0.0)
        ->and($instructorRow->training_instructor)->toBe(3.0)
        ->and($instructorRow->training_student)->toBe(0.0);
});

test('the flagged table always excludes unrostered controllers regardless of the rostered_only toggle', function () {
    $rosteredLowHours = User::factory()->create(['rostered' => true, 'facility' => config('app.vatusa_facility')]);
    $unrosteredLowHours = User::factory()->create(['rostered' => false, 'facility' => config('app.vatusa_facility')]);

    foreach ([$rosteredLowHours, $unrosteredLowHours] as $user) {
        ControllerMonthlyStat::create([
            'user_id' => $user->id, 'year' => 2026, 'month' => 7,
            'delivery_hours' => 1, 'ground_hours' => 0, 'tower_hours' => 0,
            'approach_hours' => 0, 'center_hours' => 0,
        ]);
    }

    $admin = User::factory()->create();
    $admin->assignRole('admin', 'staff');

    $response = $this->actingAs($admin)
        ->get(route('statistics.quarterly', ['year' => 2026, 'quarter' => 3, 'threshold' => 3]))
        ->assertOk();

    $flaggedIds = $response->viewData('flagged')->getCollection()->pluck('user.id');

    expect($flaggedIds)->toContain($rosteredLowHours->id)
        ->and($flaggedIds)->not->toContain($unrosteredLowHours->id);
});

test('the rostered_only toggle filters the All Controllers table', function () {
    $rostered = User::factory()->create(['rostered' => true, 'facility' => config('app.vatusa_facility')]);
    $unrostered = User::factory()->create(['rostered' => false, 'facility' => config('app.vatusa_facility')]);

    foreach ([$rostered, $unrostered] as $user) {
        ControllerMonthlyStat::create([
            'user_id' => $user->id, 'year' => 2026, 'month' => 7,
            'delivery_hours' => 5, 'ground_hours' => 0, 'tower_hours' => 0,
            'approach_hours' => 0, 'center_hours' => 0,
        ]);
    }

    $admin = User::factory()->create();
    $admin->assignRole('admin', 'staff');

    $withUnrostered = $this->actingAs($admin)
        ->get(route('statistics.quarterly', ['year' => 2026, 'quarter' => 3]))
        ->viewData('rows')->getCollection()->pluck('user.id');

    expect($withUnrostered)->toContain($unrostered->id);

    $rosteredOnly = $this->actingAs($admin)
        ->get(route('statistics.quarterly', ['year' => 2026, 'quarter' => 3, 'rostered_only' => 1]))
        ->viewData('rows')->getCollection()->pluck('user.id');

    expect($rosteredOnly)->toContain($rostered->id)
        ->and($rosteredOnly)->not->toContain($unrostered->id);
});

test('zero training hours render as 0.0h rather than a dash, in both tables', function () {
    $noTraining = User::factory()->create(['rostered' => true, 'facility' => config('app.vatusa_facility')]);

    ControllerMonthlyStat::create([
        'user_id' => $noTraining->id, 'year' => 2026, 'month' => 7,
        'delivery_hours' => 1, 'ground_hours' => 0, 'tower_hours' => 0,
        'approach_hours' => 0, 'center_hours' => 0,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin', 'staff');

    $html = $this->actingAs($admin)
        ->get(route('statistics.quarterly', ['year' => 2026, 'quarter' => 3, 'threshold' => 3]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('S 0.0 / I 0.0');
});

test('a user with the removal permission sees the multi-select and remove action on the flagged table', function () {
    $inactive = User::factory()->create(['rostered' => true]);

    ControllerMonthlyStat::create([
        'user_id' => $inactive->id, 'year' => 2026, 'month' => 7,
        'delivery_hours' => 0, 'ground_hours' => 0, 'tower_hours' => 0,
        'approach_hours' => 0, 'center_hours' => 0,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin', 'staff');

    $this->actingAs($admin)
        ->get(route('statistics.quarterly', ['year' => 2026, 'quarter' => 3, 'threshold' => 3]))
        ->assertOk()
        ->assertSee('name="user_ids[]"', false)
        ->assertSee('Remove Selected');
});

test('a user without the removal permission does not see the multi-select or remove action', function () {
    $inactive = User::factory()->create(['rostered' => true]);

    ControllerMonthlyStat::create([
        'user_id' => $inactive->id, 'year' => 2026, 'month' => 7,
        'delivery_hours' => 0, 'ground_hours' => 0, 'tower_hours' => 0,
        'approach_hours' => 0, 'center_hours' => 0,
    ]);

    $staff = User::factory()->create();
    $staff->assignRole('staff');
    // Can view the page but lacks "remove inactive controllers".
    $staff->givePermissionTo('manage visiting controllers');

    $this->actingAs($staff)
        ->get(route('statistics.quarterly', ['year' => 2026, 'quarter' => 3, 'threshold' => 3]))
        ->assertOk()
        ->assertDontSee('name="user_ids[]"', false)
        ->assertDontSee('Remove Selected');
});

test('a senior staff member can queue removal of flagged controllers', function () {
    Queue::fake();

    $inactive = User::factory()->create(['rostered' => true]);

    $admin = User::factory()->create();
    $admin->assignRole('admin', 'staff');

    $this->actingAs($admin)
        ->post(route('statistics.quarterly.remove'), [
            'user_ids' => [$inactive->id],
            'reason' => 'Inactivity — below quarterly minimum',
        ])
        ->assertRedirect();

    Queue::assertPushed(RemoveUserFromRoster::class, fn ($job) => $job->userId === $inactive->id
        && $job->reason === 'Inactivity — below quarterly minimum'
        && $job->by === $admin->id);
});

test('removal requires a reason', function () {
    Queue::fake();

    $inactive = User::factory()->create(['rostered' => true]);

    $admin = User::factory()->create();
    $admin->assignRole('admin', 'staff');

    $this->actingAs($admin)
        ->post(route('statistics.quarterly.remove'), ['user_ids' => [$inactive->id]])
        ->assertSessionHasErrors('reason');

    Queue::assertNothingPushed();
});

test('a user without the removal permission is forbidden', function () {
    Queue::fake();

    $inactive = User::factory()->create(['rostered' => true]);

    $staff = User::factory()->create();
    $staff->assignRole('staff'); // has "view dashboard" but not "remove inactive controllers"

    $this->actingAs($staff)
        ->post(route('statistics.quarterly.remove'), [
            'user_ids' => [$inactive->id],
            'reason' => 'Inactivity',
        ])
        ->assertForbidden();

    Queue::assertNothingPushed();
});

test('the removal job de-rosters the user locally and emails them on a successful VATUSA call', function () {
    Http::fake([
        '*' => Http::response(['status' => 'OK'], 200),
    ]);
    Mail::fake();

    $user = User::factory()->create([
        'rostered' => true,
        'operating_initials' => 'AB',
        'facility' => config('app.vatusa_facility'),
    ]);
    $admin = User::factory()->create();

    (new RemoveUserFromRoster($user->id, 'Inactivity', $admin->id))->handle();

    $user->refresh();

    expect($user->rostered)->toBeFalse()
        ->and($user->operating_initials)->toBe(''); // cleared (accessor upper-cases null to '')

    Mail::assertQueued(ControllerRemovedFromRoster::class, fn ($mail) => $mail->hasTo($user->email)
        && $mail->user->id === $user->id
        && $mail->reason === 'Inactivity');

    Http::assertSent(fn ($request) => $request['by'] === $admin->id);
});

test('the removal job does not email the user when the VATUSA call fails', function () {
    Http::fake([
        '*' => Http::response(['status' => 'error'], 500),
    ]);
    Mail::fake();

    $user = User::factory()->create(['rostered' => true, 'facility' => config('app.vatusa_facility')]);
    $admin = User::factory()->create();

    (new RemoveUserFromRoster($user->id, 'Inactivity', $admin->id))->handle();

    $user->refresh();

    expect($user->rostered)->toBeTrue();

    Mail::assertNotQueued(ControllerRemovedFromRoster::class);
});

test('the removal job emails the requesting staff member when the VATUSA call fails', function () {
    Http::fake([
        '*' => Http::response(['status' => 'error'], 500),
    ]);
    Mail::fake();

    $user = User::factory()->create(['rostered' => true, 'facility' => config('app.vatusa_facility')]);
    $admin = User::factory()->create();

    (new RemoveUserFromRoster($user->id, 'Inactivity', $admin->id))->handle();

    Mail::assertQueued(RosterRemovalFailed::class, fn ($mail) => $mail->hasTo($admin->email)
        && $mail->user->id === $user->id
        && $mail->reason === 'Inactivity');
});

test('the removal job treats a 200 response carrying a JSON error field as a failure', function () {
    Http::fake([
        '*' => Http::response(['error' => 'CID not found on roster'], 200),
    ]);
    Mail::fake();

    $user = User::factory()->create(['rostered' => true, 'facility' => config('app.vatusa_facility')]);
    $admin = User::factory()->create();

    (new RemoveUserFromRoster($user->id, 'Inactivity', $admin->id))->handle();

    $user->refresh();

    expect($user->rostered)->toBeTrue();

    Mail::assertNotQueued(ControllerRemovedFromRoster::class);
    Mail::assertQueued(RosterRemovalFailed::class, fn ($mail) => $mail->hasTo($admin->email)
        && $mail->user->id === $user->id);
});

test('the removal job records the VATUSA request and response on the audit log', function () {
    Http::fake([
        '*' => Http::response(['status' => 'OK'], 200),
    ]);
    Mail::fake();

    $user = User::factory()->create(['rostered' => true, 'facility' => config('app.vatusa_facility')]);
    $admin = User::factory()->create();

    (new RemoveUserFromRoster($user->id, 'Inactivity', $admin->id))->handle();

    $log = Activity::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('event', 'roster-removal-succeeded')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->causer_id)->toBe($admin->id)
        ->and($log->properties['attributes']['reason'])->toBe('Inactivity')
        ->and($log->properties['attributes']['http_status'])->toBe(200)
        ->and($log->properties['attributes']['response'])->toContain('OK');
});

test('the removal job records a failed VATUSA response on the audit log', function () {
    Http::fake([
        '*' => Http::response(['error' => 'CID not found'], 200),
    ]);
    Mail::fake();

    $user = User::factory()->create(['rostered' => true, 'facility' => config('app.vatusa_facility')]);
    $admin = User::factory()->create();

    (new RemoveUserFromRoster($user->id, 'Inactivity', $admin->id))->handle();

    $log = Activity::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('event', 'roster-removal-failed')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['attributes']['response'])->toContain('CID not found');
});

test('the removal job sends the apikey as a query parameter and the rest as a form body', function () {
    Http::fake([
        '*' => Http::response(['status' => 'OK'], 200),
    ]);
    Mail::fake();

    $user = User::factory()->create(['rostered' => true, 'facility' => config('app.vatusa_facility')]);
    $admin = User::factory()->create();

    (new RemoveUserFromRoster($user->id, 'Inactivity', $admin->id))->handle();

    Http::assertSent(function ($request) use ($admin) {
        return str_contains($request->url(), 'apikey='.config('app.vatusa_api_key'))
            && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
            && $request['reason'] === 'Inactivity'
            && $request['by'] === $admin->id;
    });
});
