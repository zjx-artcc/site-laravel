<?php

use App\Enums\ControllerRating;
use App\Enums\EventType;
use App\Enums\FeedbackExperience;
use App\Enums\FeedbackStatus;
use App\Enums\VisitRequestStatus;
use App\Jobs\AddUserToVisitingRoster;
use App\Jobs\SyncRoster;
use App\Jobs\SyncVatsimSessions;
use App\Jobs\UpdateOnlineControllers;
use App\Livewire\SectorMap;
use App\Models\AtcBooking;
use App\Models\CertificationFacility;
use App\Models\CertificationLevel;
use App\Models\Event;
use App\Models\EventPosition;
use App\Models\EventPositionPreset;
use App\Models\Feedback;
use App\Models\ManualContributor;
use App\Models\News;
use App\Models\SoloCert;
use App\Models\StaffingRequest;
use App\Models\User;
use App\Models\VisitorRequest;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function fakeVatusaRoster(array $cids): void
{
    $now = (new DateTime)->format('Y-m-d H:i:s');
    $staffCid = $cids[0] ?? 100;

    $entry = fn ($cid) => [
        'cid' => $cid, 'fname' => 'Test', 'lname' => 'Controller', 'rating' => 6,
        'email' => "test{$cid}@example.com", 'facility' => 'ZJX',
        'created_at' => $now, 'updated_at' => $now, 'facility_join' => $now,
        'flag_needbasic' => false, 'flag_xferOverride' => false,
        'flag_homecontroller' => true, 'lastactivity' => $now,
        'flag_broadcastOptedIn' => false, 'flag_preventStaffAssign' => false,
        'discord_id' => null, 'flag_nameprivacy' => false,
        'last_competency_date' => $now, 'promotion_eligible' => false,
        'transfer_eligible' => false, 'roles' => [], 'isMentor' => false,
        'isSupIns' => false, 'last_promotion' => $now,
    ];

    Http::fake([
        '*/roster/both*' => Http::response(['data' => array_map($entry, $cids)]),
        '*/v2/facility/*' => Http::response(['data' => ['facility' => [
            'info' => array_fill_keys(['atm', 'datm', 'ta', 'wm', 'ec', 'fe'], $staffCid),
            // VatusaFacilityInfoDTO only initialises its typed $roles property
            // inside the loop, so an empty list leaves it uninitialised.
            'roles' => [['cid' => $staffCid, 'role' => 'ATM', 'created_at' => $now]],
        ]]]),
    ]);
}

test('visitor request changes are recorded in the audit log', function () {
    $staff = User::factory()->create();
    $this->actingAs($staff);

    $visitRequest = VisitorRequest::create([
        'user_id' => User::factory()->create()->id,
        'user_note' => 'I would like to visit.',
        'status' => VisitRequestStatus::PENDING,
    ]);

    $visitRequest->status = VisitRequestStatus::APPROVED;
    $visitRequest->save();

    $entry = Activity::where('subject_type', VisitorRequest::class)
        ->where('event', 'updated')
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->causer_id)->toBe($staff->id);
    expect($entry->properties['attributes']['status'])->toBe(VisitRequestStatus::APPROVED->value);
});

test('solo cert issue and revoke are recorded in the audit log', function () {
    $instructor = User::factory()->create();
    $this->actingAs($instructor);

    $soloCert = SoloCert::create([
        'user_id' => User::factory()->create()->id,
        'issued_by_id' => $instructor->id,
        'position' => 'JAX_TWR',
    ]);

    expect(Activity::where('subject_type', SoloCert::class)->where('event', 'created')->exists())->toBeTrue();

    $soloCert->revoked = true;
    $soloCert->save();

    $revocation = Activity::where('subject_type', SoloCert::class)->where('event', 'updated')->first();

    expect($revocation)->not->toBeNull();
    expect($revocation->causer_id)->toBe($instructor->id);
    expect($revocation->properties['attributes']['revoked'])->toBeTrue();
});

test('contributor add and remove are recorded in the audit log', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $contributor = ManualContributor::create([
        'github_username' => 'octocat',
        'section' => 'contributor',
    ]);

    $contributor->delete();

    $removal = Activity::where('subject_type', ManualContributor::class)
        ->where('event', 'deleted')
        ->first();

    expect($removal)->not->toBeNull();
    expect($removal->causer_id)->toBe($admin->id);
    // Spatie stores the deleted row under `old`; the viewer merges both keys.
    expect($removal->properties['old']['github_username'])->toBe('octocat');
});

test('closing and reopening a staffing request is recorded in the audit log', function () {
    $staff = User::factory()->create();
    $this->actingAs($staff);

    $staffingRequest = StaffingRequest::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Need a Ground controller',
        'description' => 'Short-staffed this weekend.',
        'requested_at' => now(),
    ]);

    $staffingRequest->update(['closed' => true]);

    $closure = Activity::where('subject_type', StaffingRequest::class)
        ->where('event', 'updated')
        ->first();

    expect($closure)->not->toBeNull();
    expect($closure->causer_id)->toBe($staff->id);
    expect($closure->properties['attributes']['closed'])->toBeTrue();
});

test('event publish, lock and archive changes are recorded in the audit log', function () {
    $staff = User::factory()->create();
    $this->actingAs($staff);

    $event = Event::create([
        'title' => 'Fly-In',
        'description' => 'desc',
        'start' => now()->addDays(3),
        'end' => now()->addDays(3)->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
    ]);

    $event->update(['published' => true]);

    $publish = Activity::where('subject_type', Event::class)->where('event', 'updated')->first();

    expect($publish)->not->toBeNull();
    expect($publish->causer_id)->toBe($staff->id);
    expect($publish->properties['attributes']['published'])->toBeTrue();
    // logOnlyDirty(): only the changed attribute should be recorded.
    expect($publish->properties['attributes'])->not->toHaveKey('title');
});

test('event position assignment changes are recorded in the audit log', function () {
    $staff = User::factory()->create();
    $this->actingAs($staff);

    $event = Event::create([
        'title' => 'Fly-In',
        'description' => 'desc',
        'start' => now()->addDays(3),
        'end' => now()->addDays(3)->addHours(2),
        'type' => EventType::HOME,
        'featured_fields' => [],
        'hidden' => false,
    ]);

    $controller = User::factory()->create();

    $position = EventPosition::create([
        'event_id' => $event->id,
        'user_id' => $controller->id,
        'requested_position' => 'JAX_TWR',
        'start' => now()->addDays(3),
        'end' => now()->addDays(3)->addHours(2),
    ]);

    $position->update(['assigned_position' => 'JAX_TWR', 'position_status' => 'assigned']);

    $assignment = Activity::where('subject_type', EventPosition::class)->where('event', 'updated')->first();

    expect($assignment)->not->toBeNull();
    expect($assignment->causer_id)->toBe($staff->id);
    expect($assignment->properties['attributes']['assigned_position'])->toBe('JAX_TWR');
});

test('event position preset changes are recorded in the audit log', function () {
    $staff = User::factory()->create();
    $this->actingAs($staff);

    $preset = EventPositionPreset::create([
        'name' => 'Home Event',
        'positions' => ['JAX_TWR', 'JAX_APP'],
    ]);

    expect(Activity::where('subject_type', EventPositionPreset::class)->where('event', 'created')->first()->causer_id)
        ->toBe($staff->id);

    $preset->update(['positions' => ['JAX_TWR']]);

    $update = Activity::where('subject_type', EventPositionPreset::class)->where('event', 'updated')->first();

    expect($update)->not->toBeNull();
    expect($update->properties['attributes']['positions'])->toBe(['JAX_TWR']);
});

test('vatsim statistics sync records a completion entry even when nothing failed', function () {
    config()->set('app.vatsim_api_url', 'https://api.vatsim.net');
    config()->set('app.vatsim_statistics_page_size', 50);

    Http::fake(fn () => Http::response(['count' => 0, 'items' => []]));

    (new SyncVatsimSessions('2026-01-01T00:00:00Z', '2026-01-31T23:59:59Z'))->handle();

    $entry = Activity::where('description', 'VATSIM statistics sync page complete')->first();

    expect($entry)->not->toBeNull();
    expect($entry->properties['attributes']['sessions_accepted'])->toBe(0);
    expect($entry->properties['attributes']['sessions_skipped'])->toBe(0);
});

test('online controller sync failure is recorded in the audit log', function () {
    config()->set('app.vatsim_api_url', 'https://api.vatsim.net');

    // A 2xx response with a non-array payload: Http::retry()'s default $throw
    // means a non-2xx status never reaches this branch, so this is the path
    // that actually exercises it.
    Http::fake(fn () => Http::response('not json', 200));

    (new UpdateOnlineControllers)->handle();

    $entry = Activity::where('description', 'Online controller sync failed')->first();

    expect($entry)->not->toBeNull();
    expect($entry->properties['attributes']['status'])->toBe(200);
});

test('an unreachable VATSIM online-controller endpoint is recorded in the audit log', function () {
    config()->set('app.vatsim_api_url', 'https://api.vatsim.net');

    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    (new UpdateOnlineControllers)->handle();

    $entry = Activity::where('description', 'Online controller sync failed')->first();

    expect($entry)->not->toBeNull();
    expect($entry->properties['attributes']['exception'])->toContain('Connection refused');
});

test('adding a user to the visiting roster is recorded in the audit log', function () {
    $user = User::factory()->create();
    Http::fake(['*/roster/manageVisitor/*' => Http::response(['status' => 'OK'])]);

    (new AddUserToVisitingRoster($user->id))->handle();

    $entry = Activity::where('description', 'Added user to visiting roster')->first();

    expect($entry)->not->toBeNull();
    expect($entry->subject_id)->toBe($user->id);
    expect($entry->properties['attributes']['user_id'])->toBe($user->id);
});

test('a failed visiting roster add is recorded in the audit log', function () {
    $user = User::factory()->create();
    Http::fake(['*/roster/manageVisitor/*' => Http::response([], 500)]);

    (new AddUserToVisitingRoster($user->id))->handle();

    $entry = Activity::where('description', 'Failed to add user to visiting roster')->first();

    expect($entry)->not->toBeNull();
    expect($entry->subject_id)->toBe($user->id);
});

test('roster sync records a completion entry and each controller removed', function () {
    $departing = User::factory()->create(['id' => 200, 'rostered' => true]);
    fakeVatusaRoster([100]);

    (new SyncRoster)->handle();

    expect(Activity::where('description', 'Roster sync complete')->exists())->toBeTrue();

    $removal = Activity::where('description', 'Removed from roster')->first();

    expect($removal)->not->toBeNull();
    expect($removal->subject_id)->toBe($departing->id);
    // VATUSA dropped them, not a staff member, so there is no causer.
    expect($removal->causer_id)->toBeNull();
});

test('staff position assignment and removal are recorded in the audit log', function () {
    $firstAtm = User::factory()->create(['id' => 300]);
    $secondAtm = User::factory()->create(['id' => 301]);

    $now = (new DateTime)->format('Y-m-d H:i:s');
    $currentStaffCid = 300;

    $rosterEntry = fn (int $cid) => [
        'cid' => $cid, 'fname' => 'Test', 'lname' => 'Controller', 'rating' => 6,
        'email' => "test{$cid}@example.com", 'facility' => 'ZJX',
        'created_at' => $now, 'updated_at' => $now, 'facility_join' => $now,
        'flag_needbasic' => false, 'flag_xferOverride' => false,
        'flag_homecontroller' => true, 'lastactivity' => $now,
        'flag_broadcastOptedIn' => false, 'flag_preventStaffAssign' => false,
        'discord_id' => null, 'flag_nameprivacy' => false,
        'last_competency_date' => $now, 'promotion_eligible' => false,
        'transfer_eligible' => false, 'roles' => [], 'isMentor' => false,
        'isSupIns' => false, 'last_promotion' => $now,
    ];

    // A single fake reading a by-reference variable, rather than two separate
    // Http::fake() calls: Http::fake() stacks stubs and resolves to the FIRST
    // match, so a second call wouldn't actually override the first roster/cid.
    Http::fake(function ($request) use (&$currentStaffCid, $rosterEntry, $now) {
        $url = $request->url();

        if (str_contains($url, '/roster/both')) {
            return Http::response(['data' => [$rosterEntry($currentStaffCid)]]);
        }

        if (str_contains($url, '/v2/facility/')) {
            return Http::response(['data' => ['facility' => [
                'info' => array_fill_keys(['atm', 'datm', 'ta', 'wm', 'ec', 'fe'], $currentStaffCid),
                'roles' => [['cid' => $currentStaffCid, 'role' => 'ATM', 'created_at' => $now]],
            ]]]);
        }

        return Http::response([], 404);
    });

    (new SyncRoster)->handle();

    $assigned = Activity::where('description', 'Staff position assigned')
        ->where('subject_id', $firstAtm->id)
        ->first();

    expect($assigned)->not->toBeNull();
    expect($assigned->properties['attributes']['title'])->toBe('ATM');

    $currentStaffCid = 301;
    (new SyncRoster)->handle();

    $removed = Activity::where('description', 'Removed from staff position')
        ->where('subject_id', $firstAtm->id)
        ->first();

    expect($removed)->not->toBeNull();
    expect($removed->properties['old']['title'])->toBe('ATM');
    expect(Activity::where('description', 'Staff position assigned')->where('subject_id', $secondAtm->id)->exists())
        ->toBeTrue();
});

test('job entries render on the admin audit log page', function () {
    config()->set('app.vatsim_api_url', 'https://api.vatsim.net');
    config()->set('app.vatsim_statistics_page_size', 50);

    Http::fake(fn () => Http::response(['count' => 0, 'items' => []]));
    (new SyncVatsimSessions('2026-01-01T00:00:00Z', '2026-01-31T23:59:59Z'))->handle();

    $staff = User::factory()->create();
    $staff->givePermissionTo('view dashboard', 'view audit logs');

    $response = $this->actingAs($staff)->get(route('logs.index'));

    $response->assertStatus(200);
    $response->assertSee('VATSIM statistics sync page complete');
    $response->assertSee('Sessions Accepted');
});

test('feedback status changes are recorded in the audit log', function () {
    $staff = User::factory()->create();
    $this->actingAs($staff);

    $feedback = Feedback::create([
        'user_id' => User::factory()->create()->id,
        'controller_id' => User::factory()->create()->id,
        'position' => 'JAX_TWR',
        'experience' => FeedbackExperience::GOOD,
        'comments' => 'Great service.',
        'status' => FeedbackStatus::PENDING,
    ]);

    $feedback->update(['status' => FeedbackStatus::STASHED]);

    $stash = Activity::where('subject_type', Feedback::class)->where('event', 'updated')->first();

    expect($stash)->not->toBeNull();
    expect($stash->causer_id)->toBe($staff->id);
    expect($stash->properties['attributes']['status'])->toBe(FeedbackStatus::STASHED->value);
});

test('releasing feedback is recorded in the audit log even though the update bypasses Eloquent events', function () {
    $staff = User::factory()->create();
    $staff->givePermissionTo('view dashboard', 'feedback:read', 'feedback:write');
    $this->actingAs($staff);

    $feedback = Feedback::create([
        'user_id' => User::factory()->create()->id,
        'controller_id' => User::factory()->create(['rostered' => true])->id,
        'position' => 'JAX_TWR',
        'experience' => FeedbackExperience::GOOD,
        'comments' => 'Great service.',
        'status' => FeedbackStatus::PENDING,
    ]);

    $this->put(route('admin.feedback.release', $feedback));

    $release = Activity::where('description', 'Feedback released')->first();

    expect($release)->not->toBeNull();
    expect($release->subject_id)->toBe($feedback->id);
    expect($release->causer_id)->toBe($staff->id);
    expect($release->properties['attributes']['status'])->toBe(FeedbackStatus::RELEASED->value);
});

test('certification facility and level changes are recorded in the audit log', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $facility = CertificationFacility::create(['identifier' => 'zjx', 'name' => 'Jacksonville', 'order' => 1]);

    expect(Activity::where('subject_type', CertificationFacility::class)->where('event', 'created')->first()->causer_id)
        ->toBe($admin->id);

    $facility->update(['name' => 'Jacksonville ARTCC']);

    expect(Activity::where('subject_type', CertificationFacility::class)->where('event', 'updated')->exists())->toBeTrue();

    $level = CertificationLevel::create([
        'facility_id' => $facility->id,
        'level' => 1,
        'name' => 'Ground Control',
        'abbreviation' => 'GC',
    ]);

    $level->update(['name' => 'Ground']);
    $level->delete();

    expect(Activity::where('subject_type', CertificationLevel::class)->where('event', 'created')->exists())->toBeTrue();
    expect(Activity::where('subject_type', CertificationLevel::class)->where('event', 'updated')->exists())->toBeTrue();
    expect(Activity::where('subject_type', CertificationLevel::class)->where('event', 'deleted')->exists())->toBeTrue();
});

test('news article changes are recorded in the audit log', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $news = News::create(['title' => 'New Policy', 'content' => 'Details.', 'published_at' => now()]);

    $news->update(['title' => 'Updated Policy']);

    $update = Activity::where('subject_type', News::class)->where('event', 'updated')->first();

    expect($update)->not->toBeNull();
    expect($update->causer_id)->toBe($admin->id);
    expect($update->properties['attributes']['title'])->toBe('Updated Policy');
});

test('an ATC booking is recorded in the audit log', function () {
    $controller = User::factory()->create();
    $this->actingAs($controller);

    AtcBooking::create([
        'user_id' => $controller->id,
        'position' => 'JAX_TWR',
        'start' => now()->addHour(),
        'end' => now()->addHours(2),
    ]);

    $entry = Activity::where('subject_type', AtcBooking::class)->where('event', 'created')->first();

    expect($entry)->not->toBeNull();
    expect($entry->causer_id)->toBe($controller->id);
    expect($entry->properties['attributes']['position'])->toBe('JAX_TWR');
});

test('saving a sector map split is recorded in the audit log', function () {
    $staff = User::factory()->create(['rating' => ControllerRating::C1]);
    $this->actingAs($staff);

    Livewire::test(SectorMap::class)
        ->call('setSplit', 'high')
        ->call('enableEdit')
        ->call('assignSector', 1)
        ->call('save');

    $entry = Activity::where('description', 'Sector map split saved')->first();

    expect($entry)->not->toBeNull();
    expect($entry->causer_id)->toBe($staff->id);
    expect($entry->properties['attributes']['split'])->toBe('high');
});
