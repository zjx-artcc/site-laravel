<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(fn () => $this->seed(PermissionSeeder::class));

function fakeVatsimLogin(array $attributes): void
{
    $socialiteUser = (new SocialiteUser)->map($attributes);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('vatsim')->andReturn($provider);
}

test('login does not overwrite a rostered user\'s VATUSA facility', function () {
    // facility is owned by the roster sync; a null VATSIM subdivision must not blank it.
    $user = User::factory()->create([
        'id' => 999001,
        'facility' => 'ZJX',
        'rostered' => true,
        'first_name' => 'Old',
    ]);

    fakeVatsimLogin([
        'cid' => 999001,
        'first_name' => 'Updated',
        'last_name' => 'Name',
        'email' => 'updated@example.com',
        'division' => 'USA',
        'facility' => null, // VATSIM subdivision null for VATUSA controllers
        'rating' => 5,
    ]);

    $this->get(route('auth.callback'));

    $user->refresh();
    expect($user->facility)->toBe('ZJX');        // preserved, not clobbered
    expect($user->first_name)->toBe('Updated');  // other fields still sync from VATSIM
});

test('a brand-new user can log in with a null subdivision without error', function () {
    fakeVatsimLogin([
        'cid' => 999002,
        'first_name' => 'New',
        'last_name' => 'Controller',
        'email' => 'new@example.com',
        'division' => 'USA',
        'facility' => null,
        'rating' => 2,
    ]);

    $this->get(route('auth.callback'))->assertRedirect();

    $created = User::find(999002);
    expect($created)->not->toBeNull();
    expect($created->facility)->toBeNull();
});

test('login does not undo a VATUSA name-privacy redaction applied by the roster sync', function () {
    // last_name here mirrors what SyncRoster stores for a name-privacy user: their own CID.
    $user = User::factory()->create([
        'id' => 999003,
        'last_name' => '999003',
        'rostered' => true,
    ]);

    fakeVatsimLogin([
        'cid' => 999003,
        'first_name' => 'Updated',
        'last_name' => 'RealName', // VATSIM Connect always reports the real name
        'email' => 'private@example.com',
        'division' => 'USA',
        'facility' => null,
        'rating' => 5,
    ]);

    $this->get(route('auth.callback'));

    $user->refresh();
    expect($user->last_name)->toBe('999003');    // redaction preserved
    expect($user->first_name)->toBe('Updated');  // other fields still sync from VATSIM
});

test('login still syncs the real last name for a user without name privacy enabled', function () {
    $user = User::factory()->create([
        'id' => 999004,
        'last_name' => 'OldName',
        'rostered' => true,
    ]);

    fakeVatsimLogin([
        'cid' => 999004,
        'first_name' => 'Test',
        'last_name' => 'NewName',
        'email' => 'public@example.com',
        'division' => 'USA',
        'facility' => null,
        'rating' => 5,
    ]);

    $this->get(route('auth.callback'));

    expect($user->fresh()->last_name)->toBe('NewName');
});

test('a brand-new user with a numeric last name matching their CID logs in without being treated as redacted', function () {
    // Edge case for the redaction heuristic: no existing row, so last_name must still be written.
    fakeVatsimLogin([
        'cid' => 999005,
        'first_name' => 'New',
        'last_name' => '999005',
        'email' => 'edge@example.com',
        'division' => 'USA',
        'facility' => null,
        'rating' => 2,
    ]);

    $this->get(route('auth.callback'))->assertRedirect();

    expect(User::find(999005)->last_name)->toBe('999005');
});
