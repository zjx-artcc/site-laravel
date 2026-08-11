<?php

use App\Enums\VisitRequestStatus;
use App\Jobs\SyncRoster;
use App\Jobs\SyncStatsimSessions;
use App\Models\ManualContributor;
use App\Models\SoloCert;
use App\Models\User;
use App\Models\VisitorRequest;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Http;
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

test('statsim sync records a completion entry even when nothing failed', function () {
    Http::fake(['api.statsim.net/*' => Http::response([])]);

    (new SyncStatsimSessions(2026, 1))->handle();

    $entry = Activity::where('description', 'Statsim sync complete')->first();

    expect($entry)->not->toBeNull();
    expect($entry->properties['attributes']['month'])->toBe('2026-01');
    expect($entry->properties['attributes']['sessions_received'])->toBe(0);
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

test('job entries render on the admin audit log page', function () {
    Http::fake(['api.statsim.net/*' => Http::response([])]);
    (new SyncStatsimSessions(2026, 1))->handle();

    $staff = User::factory()->create();
    $staff->givePermissionTo('view dashboard', 'view audit logs');

    $response = $this->actingAs($staff)->get(route('logs.index'));

    $response->assertStatus(200);
    $response->assertSee('Statsim sync complete');
    $response->assertSee('Sessions Received');
});
