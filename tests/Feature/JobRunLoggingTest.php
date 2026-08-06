<?php

use App\Jobs\SyncRoster;
use App\Jobs\SyncStatsimSessions;
use App\Jobs\UpdateOnlineControllers;
use App\Models\ControllerSession;
use App\Models\OnlineController;
use App\Models\StatisticsPrefixes;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

/**
 * Build one Statsim API session payload.
 */
function statsimSession(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'callsign' => 'JAX_CTR',
        'vatsimid' => '100',
        'loggedOn' => '2026-01-05 12:00:00',
        'loggedOff' => '2026-01-05 14:00:00',
    ], $overrides);
}

function fakeStatsim(array $sessions): void
{
    Http::fake([
        'api.statsim.net/*' => Http::response($sessions),
    ]);
}

/**
 * The roster payload shape VATUSA returns, trimmed to what the sync reads.
 */
function vatusaRosterEntry(int $cid): array
{
    $now = (new DateTime)->format('Y-m-d H:i:s');

    return [
        'cid' => $cid,
        'fname' => 'Test',
        'lname' => 'Controller',
        'rating' => 6,
        'email' => "test{$cid}@example.com",
        'facility' => 'ZJX',
        'created_at' => $now,
        'updated_at' => $now,
        'flag_needbasic' => false,
        'flag_xferOverride' => false,
        'facility_join' => $now,
        'flag_homecontroller' => true,
        'lastactivity' => $now,
        'flag_broadcastOptedIn' => false,
        'flag_preventStaffAssign' => false,
        'discord_id' => null,
        'flag_nameprivacy' => false,
        'last_competency_date' => $now,
        'promotion_eligible' => false,
        'transfer_eligible' => false,
        'roles' => [],
        'isMentor' => false,
        'isSupIns' => false,
        'last_promotion' => $now,
    ];
}

beforeEach(function () {
    // Creating a User assigns the default 'core' role, and the roster sync
    // assigns 'rostered', so the permission tables must exist in every test.
    $this->seed(PermissionSeeder::class);
});

function fakeVatusaRoster(array $cids): void
{
    // VatusaFacilityInfoDTO requires every senior-staff slot to be present, so
    // point them all at the first rostered controller.
    $staffCid = $cids[0] ?? 100;

    Http::fake([
        '*/roster/both*' => Http::response([
            'data' => array_map(fn ($cid) => vatusaRosterEntry($cid), $cids),
        ]),
        '*/v2/facility/*' => Http::response([
            'data' => [
                'facility' => [
                    'info' => [
                        'atm' => $staffCid,
                        'datm' => $staffCid,
                        'ta' => $staffCid,
                        'wm' => $staffCid,
                        'ec' => $staffCid,
                        'fe' => $staffCid,
                    ],
                    // At least one role is required: VatusaFacilityInfoDTO only
                    // initialises its typed $roles property inside the loop, so
                    // an empty list leaves it uninitialised and Staff blows up.
                    'roles' => [
                        ['cid' => $staffCid, 'role' => 'ATM', 'created_at' => (new DateTime)->format('Y-m-d H:i:s')],
                    ],
                ],
            ],
        ]),
    ]);
}

// ---------------------------------------------------------------------------
// The issue's headline complaint: a sync that works silently is a sync you
// cannot tell apart from a sync that never ran.
// ---------------------------------------------------------------------------

test('given a statsim sync that succeeds, when it completes, then it logs a summary rather than staying silent', function () {
    $logs = captureLogs();

    StatisticsPrefixes::create(['name' => 'JAX']);
    User::factory()->create(['id' => 100, 'rostered' => true]);

    fakeStatsim([statsimSession()]);

    (new SyncStatsimSessions(2026, 1))->handle();

    $completed = findLog($logs, 'SyncStatsimSessions completed');

    expect($completed)->not->toBeNull();
    expect($completed['level'])->toBe('INFO');
    expect($completed['context']['sessions_received'])->toBe(1);
    expect($completed['context']['sessions_stored'])->toBe(1);
    expect($completed['context']['controllers_recomputed'])->toBe(1);
    expect($completed['context'])->toHaveKey('duration_ms');
});

test('given a statsim sync with nothing to do, when it completes, then it still reports the empty run', function () {
    $logs = captureLogs();

    fakeStatsim([]);

    (new SyncStatsimSessions(2026, 1))->handle();

    $completed = findLog($logs, 'SyncStatsimSessions completed');

    expect($completed)->not->toBeNull();
    expect($completed['context']['sessions_received'])->toBe(0);
});

test('given a statsim sync, when sessions are skipped, then the summary says why', function () {
    $logs = captureLogs();

    StatisticsPrefixes::create(['name' => 'JAX']);
    // 100 is rostered; 200 is not, and ATL_CTR is another facility's traffic.
    User::factory()->create(['id' => 100, 'rostered' => true]);

    fakeStatsim([
        statsimSession(['id' => 1]),
        statsimSession(['id' => 2, 'vatsimid' => '200']),
        statsimSession(['id' => 3, 'callsign' => 'ATL_CTR']),
        statsimSession(['id' => 4, 'loggedOff' => null]),
    ]);

    (new SyncStatsimSessions(2026, 1))->handle();

    $context = findLog($logs, 'SyncStatsimSessions completed')['context'];

    expect($context['sessions_received'])->toBe(4);
    expect($context['sessions_stored'])->toBe(1);
    expect($context['skipped_unrostered'])->toBe(1);
    expect($context['skipped_foreign_prefix'])->toBe(1);
    expect($context['skipped_incomplete'])->toBe(1);
});

test('given the statsim api fails, when the sync runs, then it logs an error with the upstream status', function () {
    $logs = captureLogs();

    Http::fake(['api.statsim.net/*' => Http::response('upstream exploded', 503)]);

    (new SyncStatsimSessions(2026, 1))->handle();

    $aborted = findLog($logs, 'SyncStatsimSessions aborted');

    expect($aborted)->not->toBeNull();
    expect($aborted['level'])->toBe('ERROR');
    expect($aborted['context']['status'])->toBe(503);
    expect(findLog($logs, 'SyncStatsimSessions completed'))->toBeNull();
});

// ---------------------------------------------------------------------------
// Correlation + debug logging
// ---------------------------------------------------------------------------

test('given a job run, when it logs, then every line shares one run id', function () {
    $logs = captureLogs();

    fakeStatsim([]);

    (new SyncStatsimSessions(2026, 1))->handle();

    $started = findLog($logs, 'SyncStatsimSessions started');
    $completed = findLog($logs, 'SyncStatsimSessions completed');

    expect($started['level'])->toBe('DEBUG');
    expect($started['context']['run_id'])->not->toBeEmpty();
    expect($completed['context']['run_id'])->toBe($started['context']['run_id']);
});

test('given two runs of the same job, when they log, then their run ids differ', function () {
    $logs = captureLogs();

    fakeStatsim([]);

    (new SyncStatsimSessions(2026, 1))->handle();
    $first = findLog($logs, 'SyncStatsimSessions started')['context']['run_id'];

    $logs->clear();

    (new SyncStatsimSessions(2026, 2))->handle();
    $second = findLog($logs, 'SyncStatsimSessions started')['context']['run_id'];

    expect($second)->not->toBe($first);
});

test('given a roster sync, when it runs, then debug logging exposes each phase', function () {
    $logs = captureLogs();

    fakeVatusaRoster([100]);

    (new SyncRoster)->handle();

    expect(findLog($logs, 'fetching roster from VATUSA'))->not->toBeNull();
    expect(findLog($logs, 'fetching facility info from VATUSA'))->not->toBeNull();
    expect(findLog($logs, 'roster membership diff'))->not->toBeNull();
    expect(findLog($logs, 'rostered role synced'))->not->toBeNull();
});

test('given a roster sync, when it completes, then the summary reports membership counts', function () {
    $logs = captureLogs();

    User::factory()->create(['id' => 200, 'rostered' => true]);
    fakeVatusaRoster([100]);

    (new SyncRoster)->handle();

    $context = findLog($logs, 'SyncRoster completed')['context'];

    expect($context['roster_size_reported'])->toBe(1);
    expect($context['controllers_joined'])->toBe(1);
    expect($context['controllers_departed'])->toBe(1);
});

test('given the vatusa roster api fails, when the sync runs, then the failure is logged with the exception', function () {
    $logs = captureLogs();

    Http::fake(['*' => Http::response('nope', 500)]);

    (new SyncRoster)->handle();

    $failed = findLog($logs, 'SyncRoster failed');

    expect($failed)->not->toBeNull();
    expect($failed['level'])->toBe('ERROR');
    expect($failed['context'])->toHaveKey('exception');
    expect($failed['context'])->toHaveKey('origin');
});

// ---------------------------------------------------------------------------
// Roster departures are membership removals, so they belong in the audit trail.
// ---------------------------------------------------------------------------

test('given a controller leaves the roster, when the sync runs, then the removal is recorded in the audit log', function () {
    $departing = User::factory()->create(['id' => 200, 'rostered' => true]);
    fakeVatusaRoster([100]);

    (new SyncRoster)->handle();

    $entry = Activity::where('event', 'roster.user-removed')->first();

    expect($entry)->not->toBeNull();
    expect($entry->subject_id)->toBe($departing->id);
    // VATUSA dropped them, not a staff member — so there is no causer.
    expect($entry->causer_id)->toBeNull();
    expect($entry->properties['attributes']['cid'])->toBe($departing->id);
});

test('given nobody leaves the roster, when the sync runs, then no removal is recorded', function () {
    User::factory()->create(['id' => 100, 'rostered' => true]);
    fakeVatusaRoster([100]);

    (new SyncRoster)->handle();

    expect(Activity::where('event', 'roster.user-removed')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// The every-minute job: quiet on success, loud on failure, and it must not
// wipe the table when the upstream call fails.
// ---------------------------------------------------------------------------

test('given the online controller sync succeeds, when it completes, then the summary stays at debug level', function () {
    $logs = captureLogs();

    StatisticsPrefixes::create(['name' => 'JAX']);
    Http::fake(['*' => Http::response([
        ['id' => 100, 'callsign' => 'JAX_CTR', 'start' => now()->toDateTimeString()],
    ])]);

    (new UpdateOnlineControllers)->handle();

    $completed = findLog($logs, 'UpdateOnlineControllers completed');

    expect($completed)->not->toBeNull();
    expect($completed['level'])->toBe('DEBUG');
});

test('given the vatsim api fails, when the online controller sync runs, then it logs an error and keeps the existing rows', function () {
    $logs = captureLogs();

    OnlineController::create([
        'callsign' => 'JAX_CTR',
        'user_id' => 100,
        'start' => now(),
    ]);

    Http::fake(['*' => Http::response('gateway timeout', 504)]);

    (new UpdateOnlineControllers)->handle();

    $aborted = findLog($logs, 'UpdateOnlineControllers aborted');

    expect($aborted)->not->toBeNull();
    expect($aborted['level'])->toBe('ERROR');
    expect($aborted['context']['status'])->toBe(504);
    // The old truncate-then-fetch order emptied the table on every upstream blip.
    expect(OnlineController::count())->toBe(1);
});

test('given a statsim sync stores sessions, when it finishes, then the sessions are actually persisted', function () {
    StatisticsPrefixes::create(['name' => 'JAX']);
    User::factory()->create(['id' => 100, 'rostered' => true]);

    fakeStatsim([statsimSession()]);

    (new SyncStatsimSessions(2026, 1))->handle();

    expect(ControllerSession::count())->toBe(1);
});
