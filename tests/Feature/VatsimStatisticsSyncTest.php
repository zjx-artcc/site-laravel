<?php

use App\Jobs\SyncVatsimMemberSessions;
use App\Jobs\SyncVatsimSessions;
use App\Models\ControllerMonthlyStat;
use App\Models\ControllerSession;
use App\Models\StatisticsPrefixes;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

test('the monthly VATSIM sync queues one member job for each rostered controller', function () {
    $first = User::factory()->create(['rostered' => true]);
    $second = User::factory()->create(['rostered' => true]);
    User::factory()->create(['rostered' => false]);

    Queue::fake();

    (new SyncVatsimSessions(2026, 8))->handle();

    Queue::assertPushed(SyncVatsimMemberSessions::class, 2);
    Queue::assertPushed(SyncVatsimMemberSessions::class, fn (SyncVatsimMemberSessions $job) => $job->userId === $first->id && $job->year === 2026 && $job->month === 8);
    Queue::assertPushed(SyncVatsimMemberSessions::class, fn (SyncVatsimMemberSessions $job) => $job->userId === $second->id && $job->year === 2026 && $job->month === 8);
});

test('the member VATSIM sync reduces the final page limit to the remaining record count', function () {
    $user = User::factory()->create(['id' => 1234567, 'rostered' => true]);
    StatisticsPrefixes::create(['name' => 'JAX']);
    $pages = [];

    Queue::fake();

    Http::fake(function (Request $request) use (&$pages, $user) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $pages[] = ['limit' => (int) $query['limit'], 'offset' => (int) $query['offset']];

        $offset = (int) $query['offset'];
        $limit = (int) $query['limit'];
        $items = array_map(fn (int $id) => [
            'connection_id' => [
                'id' => $id,
                'vatsim_id' => (string) $user->id,
                'callsign' => "JAX_{$id}_APP",
                'start' => '2026-08-15T00:00:00+00:00',
                'end' => '2026-08-15T01:00:00+00:00',
            ],
        ], range($offset + 1, min($offset + $limit, 101)));

        return Http::response(['items' => $items, 'count' => 101]);
    });

    (new SyncVatsimMemberSessions($user->id, 2026, 8))->handle();

    Queue::assertPushed(
        SyncVatsimMemberSessions::class,
        fn (SyncVatsimMemberSessions $job) => $job->userId === $user->id && $job->offset === 100 && $job->total === 101,
    );
    expect(ControllerMonthlyStat::count())->toBe(0);

    (new SyncVatsimMemberSessions($user->id, 2026, 8, 100, 101))->handle();

    expect($pages)->toBe([
        ['limit' => 100, 'offset' => 0],
        ['limit' => 1, 'offset' => 100],
    ]);
    expect(ControllerSession::count())->toBe(101);

    $stat = ControllerMonthlyStat::sole();
    expect($stat->approach_hours)->toBe(101.0)
        ->and($stat->totalHours())->toBe(101.0);
});

test('the member VATSIM sync uses the shared per-environment page rate limit', function () {
    $job = new SyncVatsimMemberSessions(1234567, 2026, 8);
    $limiter = app(RateLimiter::class)->limiter('vatsim-atc-sessions');
    $limit = $limiter($job);

    expect($job->middleware())->toHaveCount(1)
        ->and($limit->maxAttempts)->toBe(3)
        ->and($limit->decaySeconds)->toBe(60);
});

test('the member VATSIM sync stops after the page that reaches before the requested month', function () {
    $user = User::factory()->create(['id' => 1234569, 'rostered' => true]);
    StatisticsPrefixes::create(['name' => 'JAX']);

    Queue::fake();
    Http::fake([
        "https://api.vatsim.net/v2/members/{$user->id}/atc*" => Http::response([
            'count' => 200,
            'items' => [
                ['connection_id' => [
                    'id' => 1,
                    'vatsim_id' => (string) $user->id,
                    'callsign' => 'JAX_1_APP',
                    'start' => '2026-08-15T00:00:00+00:00',
                    'end' => '2026-08-15T01:00:00+00:00',
                ]],
                ['connection_id' => [
                    'id' => 2,
                    'vatsim_id' => (string) $user->id,
                    'callsign' => 'JAX_2_APP',
                    'start' => '2026-07-31T23:00:00+00:00',
                    'end' => '2026-08-01T00:00:00+00:00',
                ]],
            ],
        ]),
    ]);

    (new SyncVatsimMemberSessions($user->id, 2026, 8))->handle();

    Queue::assertNothingPushed();
    expect(ControllerSession::orderBy('id')->pluck('id')->all())->toBe([1, 2]);
    expect(ControllerMonthlyStat::sole()->approach_hours)->toBe(1.0);
});

test('the member VATSIM sync stores all sessions with a statistics prefix but totals only the requested month', function () {
    $user = User::factory()->create(['id' => 1234568, 'rostered' => true]);
    StatisticsPrefixes::create(['name' => 'JAX']);

    Http::fake([
        "https://api.vatsim.net/v2/members/{$user->id}/atc*" => Http::response([
            'count' => 3,
            'items' => [
                ['connection_id' => [
                    'id' => 1,
                    'vatsim_id' => (string) $user->id,
                    'callsign' => 'JAX_1_APP',
                    'start' => '2026-08-15T00:00:00+00:00',
                    'end' => '2026-08-15T01:00:00+00:00',
                ]],
                ['connection_id' => [
                    'id' => 2,
                    'vatsim_id' => (string) $user->id,
                    'callsign' => 'ZMA_1_APP',
                    'start' => '2026-08-15T00:00:00+00:00',
                    'end' => '2026-08-15T01:00:00+00:00',
                ]],
                ['connection_id' => [
                    'id' => 3,
                    'vatsim_id' => (string) $user->id,
                    'callsign' => 'JAX_2_APP',
                    'start' => '2026-09-01T00:00:00+00:00',
                    'end' => '2026-09-01T01:00:00+00:00',
                ]],
            ],
        ]),
    ]);

    (new SyncVatsimMemberSessions($user->id, 2026, 8))->handle();

    expect(ControllerSession::pluck('id')->all())->toBe([1, 3]);
    expect(ControllerMonthlyStat::sole()->approach_hours)->toBe(1.0);
});
