<?php

use App\Enums\VisitRequestStatus;
use App\Models\ManualContributor;
use App\Models\User;
use App\Models\VisitorRequest;
use App\Support\PrivilegedAction;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\ActivityLogger;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Mail::fake();
    Queue::fake();
});

// ---------------------------------------------------------------------------
// The recorder itself
// ---------------------------------------------------------------------------

test('given a privileged action, when recorded, then it lands in the activity log with actor, subject and detail', function () {
    $actor = User::factory()->create();
    $subject = User::factory()->create();

    $this->actingAs($actor);

    PrivilegedAction::record('test.action', $subject, ['reason' => 'because']);

    $entry = Activity::where('event', 'test.action')->first();

    expect($entry)->not->toBeNull();
    expect($entry->causer_id)->toBe($actor->id);
    expect($entry->subject_id)->toBe($subject->id);
    expect($entry->subject_type)->toBe(User::class);
    expect($entry->properties['attributes']['reason'])->toBe('because');
});

test('given a privileged action, when recorded, then it is also written to the application log', function () {
    $logs = captureLogs();
    $actor = User::factory()->create();

    $this->actingAs($actor);

    PrivilegedAction::record('test.action', null, ['reason' => 'because']);

    $record = findLog($logs, 'privileged action: test.action');

    expect($record)->not->toBeNull();
    expect($record['level'])->toBe('INFO');
    expect($record['context']['actor_cid'])->toBe($actor->id);
    expect($record['context']['reason'])->toBe('because');
});

test('given a privileged action from the console, when recorded, then it is tagged as a console action with no actor', function () {
    PrivilegedAction::record('test.action');

    $entry = Activity::where('event', 'test.action')->first();

    expect($entry->causer_id)->toBeNull();
    expect($entry->properties['context']['source'])->toBe('console');
});

test('given a privileged action over http, when recorded, then the request origin is captured for forensics', function () {
    $actor = User::factory()->create();
    // The contributor routes sit behind role:admin inside the admin prefix,
    // which additionally requires the 'view dashboard' permission.
    $actor->assignRole('admin');
    $actor->givePermissionTo('view dashboard');

    $this->actingAs($actor)->post(route('admin.contributors.store'), [
        'github_username' => 'octocat',
        'section' => 'contributor',
    ]);

    $entry = Activity::where('event', PrivilegedAction::CONTRIBUTOR_ADDED)->first();

    expect($entry)->not->toBeNull();
    expect($entry->properties['context']['source'])->toBe('web');
    expect($entry->properties['context']['route'])->toBe('admin.contributors.store');
    expect($entry->properties['context']['method'])->toBe('POST');
    expect($entry->properties['context'])->toHaveKey('ip');
});

test('given the activity log write fails, when an action is recorded, then the action still succeeds and the gap is logged', function () {
    $logs = captureLogs();

    // Simulate the audit table being unavailable.
    app()->bind(ActivityLogger::class, function () {
        throw new RuntimeException('activity_log is unavailable');
    });

    PrivilegedAction::record('test.action', null, ['reason' => 'because']);

    $critical = findLog($logs, 'Failed to write privileged action to the audit log');

    expect($critical)->not->toBeNull();
    expect($critical['level'])->toBe('CRITICAL');
    // The file-log trail must survive even when the database trail does not.
    expect(findLog($logs, 'privileged action: test.action'))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Visitor acceptances — named explicitly in the issue.
// ---------------------------------------------------------------------------

function pendingVisitRequest(): VisitorRequest
{
    return VisitorRequest::create([
        'user_id' => User::factory()->create(['rostered' => false])->id,
        'user_note' => 'I would like to visit.',
        'status' => VisitRequestStatus::PENDING,
    ]);
}

function visitingStaff(): User
{
    $staff = User::factory()->create();
    $staff->givePermissionTo('view dashboard', 'manage visiting controllers');

    return $staff;
}

test('given a visitor request, when it is approved, then the approval is audited with who approved it', function () {
    $staff = visitingStaff();
    $visitRequest = pendingVisitRequest();

    $this->actingAs($staff)
        ->put(route('visit.approve', $visitRequest->id), [
            'operatingInitials' => 'AB',
            'adminNotes' => 'Looks good',
        ]);

    $entry = Activity::where('event', PrivilegedAction::VISITOR_REQUEST_APPROVED)->first();

    expect($entry)->not->toBeNull();
    expect($entry->causer_id)->toBe($staff->id);
    expect($entry->properties['attributes']['cid'])->toBe($visitRequest->user_id);
    expect($entry->properties['attributes']['operating_initials'])->toBe('AB');
    expect($entry->properties['attributes']['admin_notes'])->toBe('Looks good');
});

test('given a visitor request, when it is denied, then the reason is captured in the audit trail', function () {
    $staff = visitingStaff();
    $visitRequest = pendingVisitRequest();

    $this->actingAs($staff)
        ->put(route('visit.deny', $visitRequest->id), [
            'reason' => 'Insufficient hours',
            'adminNotes' => 'Revisit in 3 months',
        ]);

    $entry = Activity::where('event', PrivilegedAction::VISITOR_REQUEST_DENIED)->first();

    expect($entry)->not->toBeNull();
    expect($entry->causer_id)->toBe($staff->id);
    expect($entry->properties['attributes']['reason'])->toBe('Insufficient hours');
});

test('given an approval that is rejected for duplicate operating initials, when it fails, then nothing is audited', function () {
    $staff = visitingStaff();
    User::factory()->create(['operating_initials' => 'AB']);
    $visitRequest = pendingVisitRequest();

    $this->actingAs($staff)
        ->put(route('visit.approve', $visitRequest->id), ['operatingInitials' => 'AB']);

    expect(Activity::where('event', PrivilegedAction::VISITOR_REQUEST_APPROVED)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Deletions
// ---------------------------------------------------------------------------

test('given a contributor is removed, when the record is deleted, then the audit entry still describes what was deleted', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('view dashboard');

    $contributor = ManualContributor::create([
        'github_username' => 'octocat',
        'section' => 'contributor',
        'note' => 'Helped with the roster page',
    ]);

    $this->actingAs($admin)->delete(route('admin.contributors.destroy', $contributor->id));

    $entry = Activity::where('event', PrivilegedAction::CONTRIBUTOR_REMOVED)->first();

    expect($entry)->not->toBeNull();
    expect($entry->causer_id)->toBe($admin->id);
    // Snapshotted before the delete, so the trail is not just a bare ID.
    expect($entry->properties['attributes']['github_username'])->toBe('octocat');
    expect($entry->properties['attributes']['note'])->toBe('Helped with the roster page');
    expect(ManualContributor::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// The existing admin viewer has to be able to render these new events.
// ---------------------------------------------------------------------------

test('given a privileged action is recorded, when staff open the audit log, then the action and its detail are shown', function () {
    $staff = visitingStaff();
    $visitRequest = pendingVisitRequest();

    $this->actingAs($staff)
        ->put(route('visit.approve', $visitRequest->id), ['operatingInitials' => 'CD']);

    $staff->givePermissionTo('view audit logs');

    $response = $this->actingAs($staff->fresh())->get(route('logs.index'));

    $response->assertStatus(200);
    $response->assertSee(PrivilegedAction::VISITOR_REQUEST_APPROVED);
    $response->assertSee('Operating Initials');
    $response->assertSee('CD');
});

test('given a privileged action is recorded, when staff export the audit log, then it appears in the csv', function () {
    $staff = visitingStaff();
    $visitRequest = pendingVisitRequest();

    $this->actingAs($staff)
        ->put(route('visit.approve', $visitRequest->id), ['operatingInitials' => 'CD']);

    $staff->givePermissionTo('view audit logs');

    $response = $this->actingAs($staff->fresh())->get(route('logs.export'));

    $response->assertStatus(200);

    $csv = $response->streamedContent();

    expect($csv)->toContain(PrivilegedAction::VISITOR_REQUEST_APPROVED);
    expect($csv)->toContain('Operating Initials: CD');
});
