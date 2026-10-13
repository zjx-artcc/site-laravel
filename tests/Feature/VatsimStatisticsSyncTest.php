<?php

use App\Jobs\SyncVatsimSessions;
use App\Models\ControllerMonthlyStat;
use App\Models\ControllerSession;
use App\Models\StatisticsPrefixes;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Cache::flush();
    $this->seed(PermissionSeeder::class);
    config()->set('app.vatsim_api_url', 'https://api.vatsim.net');
    config()->set('app.vatsim_statistics_page_size', 2);
});

function vatsimAtcSession(int $connectionId, int $cid, string $callsign, string $start, string $end): array
{
    return [
        'connection_id' => [
            'id' => $connectionId,
            'vatsim_id' => (string) $cid,
            'callsign' => $callsign,
            'start' => $start,
            'end' => $end,
        ],
    ];
}

test('it processes one Core API history page and queues the next offset', function () {
    $user = User::factory()->create(['id' => 10000001, 'rostered' => true]);
    StatisticsPrefixes::create(['name' => 'JAX']);
    Queue::fake();

    Http::fake(function ($request) use ($user) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

        expect($request->hasHeader('X-API-Key'))->toBeFalse()
            ->and($query['limit'])->toBe('2')
            ->and($query['start_date'])->toStartWith('2026-01-01T00:00:00')
            ->and($query['end_date'])->toStartWith('2026-01-31T23:59:59');

        if ($query['offset'] === '0') {
            return Http::response([
                'count' => 3,
                'items' => [
                    vatsimAtcSession(101, $user->id, 'JAX_DEL', '2026-01-10T10:00:00Z', '2026-01-10T11:30:00Z'),
                    vatsimAtcSession(102, $user->id, 'ZZZ_TWR', '2026-01-10T12:00:00Z', '2026-01-10T13:00:00Z'),
                ],
            ]);
        }

        return Http::response([
            'count' => 3,
            'items' => [
                vatsimAtcSession(103, $user->id, 'JAX_CTR', '2026-01-11T10:00:00Z', '2026-01-11T12:00:00Z'),
            ],
        ]);
    });

    (new SyncVatsimSessions('2026-01-01T00:00:00Z', '2026-01-31T23:59:59Z'))->handle();

    expect(ControllerSession::count())->toBe(1)
        ->and(ControllerSession::find(101)->callsign)->toBe('JAX_DEL')
        ->and(ControllerSession::find(103))->toBeNull()
        ->and(ControllerMonthlyStat::where('user_id', $user->id)->firstOrFail()->delivery_hours)->toBe(1.5);

    Queue::assertPushed(SyncVatsimSessions::class, function (SyncVatsimSessions $job) {
        return $job->offset === 2;
    });

    StatisticsPrefixes::query()->delete();
    $user->update(['rostered' => false]);

    $nextPage = Queue::pushed(SyncVatsimSessions::class)->sole();
    $nextPage->handle();

    expect(ControllerSession::count())->toBe(2)
        ->and(ControllerSession::find(103)->facility_level)->toBe(6);

    $monthly = ControllerMonthlyStat::where('user_id', $user->id)->firstOrFail();
    expect($monthly->delivery_hours)->toBe(1.5)
        ->and($monthly->center_hours)->toBe(2.0);

    Http::assertSentCount(2);
    Queue::assertPushed(SyncVatsimSessions::class, 1);
});

test('statistics writers can queue a Core API sync from the admin form', function () {
    Queue::fake();

    $user = User::factory()->create();
    $user->assignRole(['staff', 'facilities']);

    $this->actingAs($user)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertSee('Sync VATSIM Core Statistics')
        ->assertSee('name="from_date"', false)
        ->assertSee('name="to_date"', false);

    $this->actingAs($user)
        ->post(route('statistics.sync'), [
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-31',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    Queue::assertPushed(SyncVatsimSessions::class, function (SyncVatsimSessions $job) {
        return $job->from === '2026-01-01T00:00:00+00:00'
            && $job->to === '2026-01-31T23:59:59+00:00'
            && $job->offset === 0;
    });
});

test('the statistics sync command queues the first Core API page', function () {
    Queue::fake();

    $this->artisan('statistics:sync', [
        'from' => '2026-01-01',
        'to' => '2026-01-31',
    ])
        ->expectsOutput('Queued VATSIM Core statistics sync from 2026-01-01T00:00:00+00:00 to 2026-01-31T00:00:00+00:00.')
        ->assertSuccessful();

    Queue::assertPushed(SyncVatsimSessions::class, function (SyncVatsimSessions $job) {
        return $job->from === '2026-01-01T00:00:00+00:00'
            && $job->to === '2026-01-31T00:00:00+00:00'
            && $job->offset === 0;
    });
});

test('it throws a failed Core API request so the queue retries the page', function () {
    Http::fake(fn () => Http::response([], 503));

    (new SyncVatsimSessions('2026-01-01T00:00:00Z', '2026-01-31T23:59:59Z'))
        ->handle();
})->throws(RequestException::class);

test('statistics sync requires a valid date range', function () {
    $user = User::factory()->create();
    $user->assignRole(['staff', 'facilities']);

    $this->actingAs($user)
        ->from(route('admin.index'))
        ->post(route('statistics.sync'), [
            'from_date' => '2026-02-01',
            'to_date' => '2026-01-31',
        ])
        ->assertRedirect(route('admin.index'))
        ->assertSessionHasErrors('from_date');
});
