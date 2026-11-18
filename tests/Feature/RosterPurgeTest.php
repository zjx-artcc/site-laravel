<?php

use App\Enums\LoaStatus;
use App\Jobs\RemoveUserFromRoster;
use App\Mail\ControllerRemovedFromRoster;
use App\Mail\RosterRemovalFailed;
use App\Models\ControllerMonthlyStat;
use App\Models\Loa;
use App\Models\TrainingAssignment;
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

    // Gives both users logged stats for Q3 2026 (zero hours, so both are flagged).
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
        ->get(route('roster-purge.index', ['year' => 2026, 'quarter' => 3, 'threshold' => 100]))
        ->assertOk();

    $rows = $response->viewData('flagged');

    $studentRow = $rows->getCollection()->firstWhere('user.id', $controller->id);
    $instructorRow = $rows->getCollection()->firstWhere('user.id', $instructor->id);

    expect($studentRow->training_student)->toBe(3.0)
        ->and($studentRow->training_instructor)->toBe(0.0)
        ->and($instructorRow->training_instructor)->toBe(3.0)
        ->and($instructorRow->training_student)->toBe(0.0);
});

test('the flagged table always excludes unrostered controllers', function () {
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
        ->get(route('roster-purge.index', ['year' => 2026, 'quarter' => 3, 'threshold' => 3]))
        ->assertOk();

    $flaggedIds = $response->viewData('flagged')->getCollection()->pluck('user.id');

    expect($flaggedIds)->toContain($rosteredLowHours->id)
        ->and($flaggedIds)->not->toContain($unrosteredLowHours->id);
});

test('zero training hours render as 0.0h rather than a dash', function () {
    $noTraining = User::factory()->create(['rostered' => true, 'facility' => config('app.vatusa_facility')]);

    ControllerMonthlyStat::create([
        'user_id' => $noTraining->id, 'year' => 2026, 'month' => 7,
        'delivery_hours' => 1, 'ground_hours' => 0, 'tower_hours' => 0,
        'approach_hours' => 0, 'center_hours' => 0,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin', 'staff');

    $html = $this->actingAs($admin)
        ->get(route('roster-purge.index', ['year' => 2026, 'quarter' => 3, 'threshold' => 3]))
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
        ->get(route('roster-purge.index', ['year' => 2026, 'quarter' => 3, 'threshold' => 3]))
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
        ->get(route('roster-purge.index', ['year' => 2026, 'quarter' => 3, 'threshold' => 3]))
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
        ->post(route('roster-purge.remove'), [
            'user_ids' => [$inactive->id],
            'reason' => 'Inactivity — below quarterly minimum',
        ])
        ->assertRedirect();

    Queue::assertPushed(RemoveUserFromRoster::class, fn ($job) => $job->userId === $inactive->id
        && $job->reason === 'Inactivity — below quarterly minimum'
        && $job->by === $admin->id);
});

test('controllers queued for removal no longer appear in the flagged table', function () {
    Queue::fake();

    $queued = User::factory()->create(['rostered' => true, 'facility' => config('app.vatusa_facility')]);
    $other = User::factory()->create(['rostered' => true, 'facility' => config('app.vatusa_facility')]);

    $admin = User::factory()->create();
    $admin->assignRole('admin', 'staff');

    $this->actingAs($admin)
        ->post(route('roster-purge.remove'), ['user_ids' => [$queued->id], 'reason' => 'Inactive'])
        ->assertRedirect();

    expect($queued->fresh()->roster_removal_queued_at)->not->toBeNull();

    $flagged = $this->actingAs($admin)
        ->get(route('roster-purge.index', ['year' => 2026, 'quarter' => 3]))
        ->viewData('flagged')->getCollection()->pluck('user.id');

    expect($flagged)->not->toContain($queued->id)
        ->and($flagged)->toContain($other->id);
});

test('a controller already queued for removal is not queued again', function () {
    Queue::fake();

    $queued = User::factory()->create(['rostered' => true]);
    $queued->forceFill(['roster_removal_queued_at' => now()])->save();

    $admin = User::factory()->create();
    $admin->assignRole('admin', 'staff');

    $this->actingAs($admin)
        ->post(route('roster-purge.remove'), ['user_ids' => [$queued->id], 'reason' => 'Inactive'])
        ->assertRedirect();

    Queue::assertNothingPushed();
});

test('removal requires a reason', function () {
    Queue::fake();

    $inactive = User::factory()->create(['rostered' => true]);

    $admin = User::factory()->create();
    $admin->assignRole('admin', 'staff');

    $this->actingAs($admin)
        ->post(route('roster-purge.remove'), ['user_ids' => [$inactive->id]])
        ->assertSessionHasErrors('reason');

    Queue::assertNothingPushed();
});

test('a user without the removal permission is forbidden', function () {
    Queue::fake();

    $inactive = User::factory()->create(['rostered' => true]);

    $staff = User::factory()->create();
    $staff->assignRole('staff'); // has "view dashboard" but not "remove inactive controllers"

    $this->actingAs($staff)
        ->post(route('roster-purge.remove'), [
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
        ->and($user->operating_initials)->toBeNull(); // cleared

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

test('the removal job clears the queued flag whether VATUSA succeeds or fails', function (int $status, bool $rostered) {
    Http::fake([
        '*' => Http::response($status === 200 ? ['status' => 'OK'] : ['status' => 'error'], $status),
    ]);
    Mail::fake();

    $user = User::factory()->create(['rostered' => true, 'facility' => config('app.vatusa_facility')]);
    $user->forceFill(['roster_removal_queued_at' => now()])->save();
    $admin = User::factory()->create();

    (new RemoveUserFromRoster($user->id, 'Inactivity', $admin->id))->handle();

    $user->refresh();

    expect($user->roster_removal_queued_at)->toBeNull()
        ->and($user->rostered)->toBe($rostered);
})->with([
    'success' => [200, false],
    'failure' => [500, true],
]);

test('a removal job that throws clears the queued flag', function () {
    $user = User::factory()->create(['rostered' => true]);
    $user->forceFill(['roster_removal_queued_at' => now()])->save();

    (new RemoveUserFromRoster($user->id, 'Inactivity', $user->id))->failed(new RuntimeException('VATUSA unreachable'));

    expect($user->fresh()->roster_removal_queued_at)->toBeNull();
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

test('a flagged controller with an approved LOA is highlighted and cannot be selected for removal', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin', 'staff');
    $onLoa = User::factory()->create(['rostered' => true, 'rating' => 5, 'facility' => config('app.vatusa_facility')]);

    ControllerMonthlyStat::create([
        'user_id' => $onLoa->id, 'year' => 2026, 'month' => 7,
        'delivery_hours' => 0, 'ground_hours' => 0, 'tower_hours' => 0,
        'approach_hours' => 0, 'center_hours' => 0,
    ]);
    Loa::create([
        'user_id' => $onLoa->id,
        'start_date' => '2026-07-01',
        'end_date' => '2026-12-31',
        'reason' => 'Travel',
        'status' => LoaStatus::APPROVED,
    ]);

    $this->actingAs($admin)
        ->get(route('roster-purge.index', ['year' => 2026, 'quarter' => 3]))
        ->assertOk()
        ->assertSee('On LOA')
        ->assertDontSee('name="user_ids[]" value="'.$onLoa->id.'"', false);
});

test('removal skips controllers with an approved LOA', function () {
    Queue::fake();
    $admin = User::factory()->create();
    $admin->assignRole('admin', 'staff');
    $onLoa = User::factory()->create(['rostered' => true]);
    $other = User::factory()->create(['rostered' => true]);
    Loa::create([
        'user_id' => $onLoa->id,
        'start_date' => '2026-07-01',
        'end_date' => '2026-12-31',
        'reason' => 'Travel',
        'status' => LoaStatus::APPROVED,
    ]);

    $this->actingAs($admin)
        ->post(route('roster-purge.remove'), ['user_ids' => [$onLoa->id, $other->id], 'reason' => 'Inactive'])
        ->assertRedirect();

    Queue::assertPushed(RemoveUserFromRoster::class, 1);
    Queue::assertPushed(RemoveUserFromRoster::class, fn ($job) => $job->userId === $other->id);
});

test('the show_loa toggle hides controllers with an approved LOA from the flagged table', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin', 'staff');
    $onLoa = User::factory()->create(['rostered' => true, 'facility' => config('app.vatusa_facility')]);
    $regular = User::factory()->create(['rostered' => true, 'facility' => config('app.vatusa_facility')]);

    foreach ([$onLoa, $regular] as $user) {
        ControllerMonthlyStat::create([
            'user_id' => $user->id, 'year' => 2026, 'month' => 7,
            'delivery_hours' => 1, 'ground_hours' => 0, 'tower_hours' => 0,
            'approach_hours' => 0, 'center_hours' => 0,
        ]);
    }
    Loa::create([
        'user_id' => $onLoa->id, 'start_date' => '2026-07-01', 'end_date' => '2026-12-31',
        'reason' => 'Travel', 'status' => LoaStatus::APPROVED,
    ]);

    $query = ['year' => 2026, 'quarter' => 3];

    $shown = $this->actingAs($admin)->get(route('roster-purge.index', $query))
        ->viewData('flagged')->getCollection()->pluck('user.id');
    $hidden = $this->actingAs($admin)->get(route('roster-purge.index', $query + ['show_loa' => 0]))
        ->viewData('flagged')->getCollection()->pluck('user.id');

    expect($shown)->toContain($onLoa->id)->toContain($regular->id)
        ->and($hidden)->not->toContain($onLoa->id)
        ->and($hidden)->toContain($regular->id);
});

test('the max_training filter only flags controllers with fewer training hours than the limit', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin', 'staff');
    $trained = User::factory()->create(['rostered' => true, 'facility' => config('app.vatusa_facility')]);
    $untrained = User::factory()->create(['rostered' => true, 'facility' => config('app.vatusa_facility')]);
    $instructor = User::factory()->create(['rostered' => true, 'facility' => config('app.vatusa_facility')]);

    foreach ([$trained, $untrained] as $user) {
        ControllerMonthlyStat::create([
            'user_id' => $user->id, 'year' => 2026, 'month' => 7,
            'delivery_hours' => 1, 'ground_hours' => 0, 'tower_hours' => 0,
            'approach_hours' => 0, 'center_hours' => 0,
        ]);
    }
    makeTicket($trained->id, $instructor->id, '2026-07-05 10:00:00', '2026-07-05 13:00:00'); // 3h

    $query = ['year' => 2026, 'quarter' => 3];

    $all = $this->actingAs($admin)->get(route('roster-purge.index', $query))
        ->viewData('flagged')->getCollection()->pluck('user.id');
    $limited = $this->actingAs($admin)->get(route('roster-purge.index', $query + ['max_training' => 2]))
        ->viewData('flagged')->getCollection()->pluck('user.id');

    expect($all)->toContain($trained->id)->toContain($untrained->id)
        ->and($limited)->toContain($untrained->id)
        ->and($limited)->not->toContain($trained->id);
});

test('rostered controllers with no logged stats at all are flagged with 0 hours', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin', 'staff');
    $neverLogged = User::factory()->create(['rostered' => true, 'facility' => config('app.vatusa_facility')]);

    $flagged = $this->actingAs($admin)
        ->get(route('roster-purge.index', ['year' => 2026, 'quarter' => 3]))
        ->assertOk()
        ->viewData('flagged')->getCollection();

    expect($flagged->pluck('user.id'))->toContain($neverLogged->id)
        ->and($flagged->firstWhere('user.id', $neverLogged->id)->total)->toBe(0);
});

test('a staff member cannot remove themselves from the roster', function () {
    Queue::fake();
    $admin = User::factory()->create(['rostered' => true]);
    $admin->assignRole('admin', 'staff');
    $other = User::factory()->create(['rostered' => true]);

    $this->actingAs($admin)
        ->post(route('roster-purge.remove'), ['user_ids' => [$admin->id, $other->id], 'reason' => 'Inactive'])
        ->assertRedirect()
        ->assertSessionHas('error');

    Queue::assertNothingPushed();
});

test('hours spent instructing count towards the flag threshold', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin', 'staff');
    $instructor = User::factory()->create(['rostered' => true, 'rating' => 8, 'facility' => config('app.vatusa_facility')]);
    $student = User::factory()->create(['rostered' => true, 'rating' => 3, 'facility' => config('app.vatusa_facility')]);

    // 4h instructing, 0h logged: above a 3h threshold once instructing counts.
    makeTicket($student->id, $instructor->id, '2026-07-05 10:00:00', '2026-07-05 14:00:00');

    $flagged = $this->actingAs($admin)
        ->get(route('roster-purge.index', ['year' => 2026, 'quarter' => 3, 'threshold' => 3]))
        ->viewData('flagged')->getCollection();

    $instructorRow = $flagged->firstWhere('user.id', $instructor->id);

    expect($flagged->pluck('user.id'))->not->toContain($instructor->id)
        ->and($flagged->pluck('user.id'))->toContain($student->id);

    $withHigherThreshold = $this->actingAs($admin)
        ->get(route('roster-purge.index', ['year' => 2026, 'quarter' => 3, 'threshold' => 5]))
        ->viewData('flagged')->getCollection()->firstWhere('user.id', $instructor->id);

    expect($instructorRow)->toBeNull()
        ->and($withHigherThreshold->counted)->toBe(4.0)
        ->and($withHigherThreshold->total)->toBe(0);
});

test('observers with an active training assignment are never flagged', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin', 'staff');
    $inTraining = User::factory()->create(['rostered' => true, 'rating' => 1, 'facility' => config('app.vatusa_facility')]);
    $notInTraining = User::factory()->create(['rostered' => true, 'rating' => 1, 'facility' => config('app.vatusa_facility')]);

    TrainingAssignment::withoutSyncingToSearch(fn () => TrainingAssignment::create([
        'user_id' => $inTraining->id,
        'active' => true,
        'training_type' => 1,
        'status' => 1,
    ]));

    $flagged = $this->actingAs($admin)
        ->get(route('roster-purge.index', ['year' => 2026, 'quarter' => 3]))
        ->viewData('flagged')->getCollection()->pluck('user.id');

    expect($flagged)->not->toContain($inTraining->id)
        ->and($flagged)->toContain($notInTraining->id);
});

test('searching for a controller shows them even when they are not below the threshold', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin', 'staff');
    $active = User::factory()->create(['rostered' => true, 'facility' => config('app.vatusa_facility')]);

    ControllerMonthlyStat::create([
        'user_id' => $active->id, 'year' => 2026, 'month' => 7,
        'delivery_hours' => 20, 'ground_hours' => 0, 'tower_hours' => 0,
        'approach_hours' => 0, 'center_hours' => 0,
    ]);

    $flagged = $this->actingAs($admin)
        ->get(route('roster-purge.index', ['year' => 2026, 'quarter' => 3, 'cid' => $active->id]))
        ->assertOk()
        ->assertSee('Controller Lookup')
        ->viewData('flagged')->getCollection();

    expect($flagged->pluck('user.id')->all())->toBe([$active->id]);
});
