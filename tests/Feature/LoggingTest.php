<?php

use App\Jobs\SyncRoster;
use App\Jobs\SyncStatsimSessions;
use App\Jobs\UpdateOnlineControllers;
use App\Models\OnlineController;
use App\Models\StatisticsPrefixes;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    // Creating a User assigns the default 'core' role.
    $this->seed(PermissionSeeder::class);
});

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

test('statsim sync logs a summary on success, not only on error', function () {
    Log::spy();

    StatisticsPrefixes::create(['name' => 'JAX']);
    User::factory()->create(['id' => 100, 'rostered' => true]);

    Http::fake(['api.statsim.net/*' => Http::response([statsimSession()])]);

    (new SyncStatsimSessions(2026, 1))->handle();

    Log::shouldHaveReceived('info')->withArgs(
        fn ($message, $context) => $message === 'Statsim sync complete'
            && $context['sessions_received'] === 1
            && $context['sessions_stored'] === 1
            && $context['controllers_updated'] === 1
    )->once();
});

test('statsim sync reports skipped sessions in its summary', function () {
    Log::spy();

    StatisticsPrefixes::create(['name' => 'JAX']);
    User::factory()->create(['id' => 100, 'rostered' => true]);

    Http::fake(['api.statsim.net/*' => Http::response([
        statsimSession(['id' => 1]),
        statsimSession(['id' => 2, 'vatsimid' => '200']),   // not rostered
        statsimSession(['id' => 3, 'callsign' => 'ATL_CTR']), // other facility
    ])]);

    (new SyncStatsimSessions(2026, 1))->handle();

    Log::shouldHaveReceived('info')->withArgs(
        fn ($message, $context) => $message === 'Statsim sync complete'
            && $context['sessions_stored'] === 1
            && $context['sessions_skipped'] === 2
    )->once();
});

test('roster sync logs each controller removed from the roster', function () {
    Log::spy();

    User::factory()->create(['id' => 200, 'rostered' => true]);
    fakeVatusaRoster([100]);

    (new SyncRoster)->handle();

    Log::shouldHaveReceived('info')->withArgs(
        fn ($message, $context) => $message === 'Controller removed from roster'
            && $context['cid'] === 200
    )->once();
});

test('roster sync emits debug detail for each phase', function () {
    Log::spy();

    fakeVatusaRoster([100]);

    (new SyncRoster)->handle();

    Log::shouldHaveReceived('debug')->withArgs(
        fn ($message) => $message === 'Fetching roster from VATUSA'
    )->once();

    Log::shouldHaveReceived('debug')->withArgs(
        fn ($message) => $message === 'Roster membership diff'
    )->once();
});

test('online controller sync keeps existing rows when the vatsim api fails', function () {
    Log::spy();

    OnlineController::create(['callsign' => 'JAX_CTR', 'user_id' => 100, 'start' => now()]);

    Http::fake(['*' => Http::response('gateway timeout', 504)]);

    (new UpdateOnlineControllers)->handle();

    Log::shouldHaveReceived('error')->withArgs(
        fn ($message, $context) => $message === 'Online controller sync failed'
            && $context['status'] === 504
    )->once();

    // Previously the table was truncated before the response was checked.
    expect(OnlineController::count())->toBe(1);
});
