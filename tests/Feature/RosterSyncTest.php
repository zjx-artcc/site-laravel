<?php

use App\Jobs\SyncRoster;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Broadcasting\ShouldBeUnique as BroadcastingShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldBeUnique as QueueShouldBeUnique;
use Illuminate\Support\Facades\Http;

function rosterEntry(int $cid, string $lastName, bool $namePrivacy): array
{
    $now = (new DateTime)->format('Y-m-d H:i:s');

    return [
        'cid' => $cid,
        'fname' => 'Test',
        'lname' => $lastName,
        'rating' => 6,
        'email' => 'test'.$cid.'@test.com',
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
        'flag_nameprivacy' => $namePrivacy,
        'last_competency_date' => $now,
        'promotion_eligible' => false,
        'transfer_eligible' => false,
        'roles' => [],
        'isMentor' => false,
        'isSupIns' => false,
        'last_promotion' => $now,
    ];
}

function fakeFacilityInfo(int $atmCid): array
{
    $now = (new DateTime)->format('Y-m-d H:i:s');

    return [
        'data' => [
            'facility' => [
                'info' => [
                    'atm' => $atmCid,
                    'datm' => $atmCid,
                    'ta' => $atmCid,
                    'wm' => $atmCid,
                    'ec' => $atmCid,
                    'fe' => $atmCid,
                ],
                'roles' => [
                    ['cid' => $atmCid, 'role' => 'ATM', 'created_at' => $now],
                ],
            ],
        ],
    ];
}

test('the roster sync job is queue-unique so it cannot run twice concurrently', function () {
    $job = new SyncRoster;

    // Must implement the *queue* contract; the broadcasting one is a no-op for
    // queued jobs and silently disables the uniqueness that prevents duplicate
    // syncs at the top of the hour.
    expect($job)->toBeInstanceOf(QueueShouldBeUnique::class);
    expect($job)->not->toBeInstanceOf(BroadcastingShouldBeUnique::class);
});

test('a second roster sync dispatch is blocked while one is already locked', function () {
    $lock = new UniqueLock(app('cache')->store());

    expect($lock->acquire(new SyncRoster))->toBeTrue();        // first run acquires the lock
    expect($lock->acquire(new SyncRoster))->toBeFalse();       // duplicate is blocked

    $lock->release(new SyncRoster);

    expect($lock->acquire(new SyncRoster))->toBeTrue();        // lock frees after release
    $lock->release(new SyncRoster);
});

test('given a roster sync, when it completes, then the rostered role matches roster membership', function () {
    $this->seed(PermissionSeeder::class);

    $now = (new DateTime)->format('Y-m-d H:i:s');

    $joiningUser = User::factory()->create(['id' => 100, 'rostered' => false]);
    $leavingUser = User::factory()->create(['id' => 200, 'rostered' => true]);
    $leavingUser->assignRole('rostered');

    Http::fake([
        '*/roster/both*' => Http::response([
            'data' => [
                [
                    'cid' => 100,
                    'fname' => 'Test',
                    'lname' => 'Test',
                    'rating' => 6,
                    'email' => 'test@test.com',
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
                ],
            ],
        ]),
        '*/v2/facility/*' => Http::response([
            'data' => [
                'facility' => [
                    'info' => [
                        'atm' => 100,
                        'datm' => 100,
                        'ta' => 100,
                        'wm' => 100,
                        'ec' => 100,
                        'fe' => 100,
                    ],
                    'roles' => [
                        ['cid' => 100, 'role' => 'ATM', 'created_at' => $now],
                    ],
                ],
            ],
        ]),
    ]);

    (new SyncRoster)->handle();

    expect($joiningUser->fresh()->rostered)->toBeTrue();
    expect($joiningUser->fresh()->hasRole('rostered'))->toBeTrue();
    expect($leavingUser->fresh()->rostered)->toBeFalse();
    expect($leavingUser->fresh()->hasRole('rostered'))->toBeFalse();
});

test('given a roster sync, when a controller has VATUSA name privacy enabled, then their last name is replaced with their CID', function () {
    $this->seed(PermissionSeeder::class);

    Http::fake([
        '*/roster/both*' => Http::response([
            'data' => [rosterEntry(300, 'Private', true)],
        ]),
        '*/v2/facility/*' => Http::response(fakeFacilityInfo(300)),
    ]);

    (new SyncRoster)->handle();

    $user = User::find(300);
    expect($user->last_name)->toBe('300');
    expect($user->first_name)->toBe('Test');
});

test('given a roster sync, when a controller does not have VATUSA name privacy enabled, then their real last name is stored', function () {
    $this->seed(PermissionSeeder::class);

    Http::fake([
        '*/roster/both*' => Http::response([
            'data' => [rosterEntry(400, 'Public', false)],
        ]),
        '*/v2/facility/*' => Http::response(fakeFacilityInfo(400)),
    ]);

    (new SyncRoster)->handle();

    expect(User::find(400)->last_name)->toBe('Public');
});

test('given a controller who previously had name privacy enabled, when a later sync reports it disabled, then their real last name is restored', function () {
    $this->seed(PermissionSeeder::class);

    $user = User::factory()->create(['id' => 500, 'last_name' => '500', 'rostered' => true]);

    Http::fake([
        '*/roster/both*' => Http::response([
            'data' => [rosterEntry(500, 'Restored', false)],
        ]),
        '*/v2/facility/*' => Http::response(fakeFacilityInfo(500)),
    ]);

    (new SyncRoster)->handle();

    expect($user->fresh()->last_name)->toBe('Restored');
});
