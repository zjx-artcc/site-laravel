<?php

use App\Models\CertificationFacility;
use App\Models\CertificationLevel;
use App\Models\User;
use App\Models\UserCertification;
use Database\Seeders\PermissionSeeder;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => $this->seed(PermissionSeeder::class));

test('guests are redirected to login', function () {
    $user = User::factory()->create();

    $this->get(route('users.show', ['user' => $user->id]))->assertRedirect(route('login'));
});

test('a user profile publicly displays their certifications', function () {
    $facility = CertificationFacility::factory()->create(['identifier' => 'ZJX', 'name' => 'Jacksonville']);
    $level = CertificationLevel::factory()->create(['facility_id' => $facility->id, 'name' => 'Ground', 'abbreviation' => 'GND']);

    $user = User::factory()->create();
    UserCertification::create(['user_id' => $user->id, 'certification_level_id' => $level->id]);

    $response = $this->actingAs(User::factory()->create())->get(route('users.show', ['user' => $user->id]));

    $response->assertStatus(200);
    $response->assertSee('Certifications');
    $response->assertSee('GND');
});

test('a user profile renders when the guard reports authenticated but resolves a null user', function () {
    // Reproduces the intermittent production 500 "Call to a member function hasRole()
    // on null": the auth guard reports check() === true while user() === null, so the
    // @role/@hasrole directives used across the profile pages must be null-safe.
    $user = User::factory()->create();

    $guard = Mockery::mock(Guard::class)->shouldIgnoreMissing(null);
    $guard->shouldReceive('check')->andReturnTrue();
    $guard->shouldReceive('guest')->andReturnFalse();
    $guard->shouldReceive('user')->andReturnNull();

    Auth::extend('null-user', fn () => $guard);
    config(['auth.guards.web.driver' => 'null-user']);
    $this->app['auth']->forgetGuards();

    $this->get(route('users.show', ['user' => $user->id]))->assertStatus(200);
});

test('profile images use public-disk URLs and retain access to legacy stored paths', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'profile_image_route' => 'storage/profile/legacy-profile.png',
    ]);

    expect($user->profile_image_url)->toBe(Storage::disk('public')->url('profile/legacy-profile.png'));

    $this->actingAs(User::factory()->create())
        ->get(route('users.show', ['user' => $user->id]))
        ->assertOk()
        ->assertSee($user->profile_image_url, false);
});

test('new profile images store a public-disk path instead of a hand-built URL', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'profile_image_route' => 'storage/profile/previous-photo.jpg',
    ]);
    Storage::disk('public')->put('profile/previous-photo.jpg', 'previous image');

    $this->actingAs($user)
        ->put(route('users.update', $user), [
            'image' => UploadedFile::fake()->image('profile.png', 300, 300),
        ])
        ->assertRedirect(route('users.edit', ['user' => $user->id]));

    $user->refresh();

    expect($user->profile_image_route)->toBe("profile/profile_{$user->id}.png")
        ->and($user->profile_image_url)->toBe(Storage::disk('public')->url($user->profile_image_route));
    Storage::disk('public')->assertExists($user->profile_image_route);
    Storage::disk('public')->assertMissing('profile/previous-photo.jpg');
});
