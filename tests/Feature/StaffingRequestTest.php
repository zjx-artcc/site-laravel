<?php

use App\Mail\StaffingRequestClosed;
use App\Mail\StaffingRequestSubmitted;
use App\Models\StaffingRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

// Submitting a staffing request

test('given a guest, when visiting the staffing request form, then they are redirected to login', function () {
    $response = $this->get(route('staffing-requests.index'));

    $response->assertRedirect(route('login'));
});

test('given an authenticated user, when visiting the staffing request form, then their details are prefilled', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('staffing-requests.index'));

    $response->assertOk();
    $response->assertSee($user->name);
    $response->assertSee($user->email);
});

test('given an authenticated user, when submitting a valid staffing request, then it is stored against them and a confirmation email is sent', function () {
    Mail::fake();

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('staffing-requests.store'), [
        'name' => 'Jacksonville Fly-In',
        'description' => 'Looking for JAX_TWR and JAX_APP staffed from 2300z-0200z.',
        'requested_at' => '2026-09-01T23:00',
    ]);

    $response->assertRedirect(route('staffing-requests.index'));
    $response->assertSessionHas('success');

    $staffingRequest = StaffingRequest::sole();
    expect($staffingRequest->user_id)->toBe($user->id)
        ->and($staffingRequest->name)->toBe('Jacksonville Fly-In')
        ->and($staffingRequest->description)->toBe('Looking for JAX_TWR and JAX_APP staffed from 2300z-0200z.')
        ->and($staffingRequest->requested_at->format('Y-m-d H:i'))->toBe('2026-09-01 23:00')
        ->and($staffingRequest->closed)->toBeFalse();

    Mail::assertQueued(StaffingRequestSubmitted::class, fn ($mail) => $mail->hasTo($user->email));
});

test('given a guest, when submitting a staffing request, then they are redirected to login and nothing is stored', function () {
    $response = $this->post(route('staffing-requests.store'), [
        'name' => 'Jacksonville Fly-In',
        'description' => 'Should not be stored.',
        'requested_at' => '2026-09-01T23:00',
    ]);

    $response->assertRedirect(route('login'));
    expect(StaffingRequest::count())->toBe(0);
});

test('given an authenticated user, when submitting without an event name, then a name error is returned and nothing is stored', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('staffing-requests.store'), [
        'name' => '',
        'description' => 'Some description.',
        'requested_at' => '2026-09-01T23:00',
    ]);

    $response->assertSessionHasErrors('name');
    expect(StaffingRequest::count())->toBe(0);
});

test('given an authenticated user, when submitting without a description, then a description error is returned and nothing is stored', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('staffing-requests.store'), [
        'name' => 'Jacksonville Fly-In',
        'description' => '',
        'requested_at' => '2026-09-01T23:00',
    ]);

    $response->assertSessionHasErrors('description');
    expect(StaffingRequest::count())->toBe(0);
});

test('given an authenticated user, when submitting without a requested date/time, then a requested_at error is returned and nothing is stored', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('staffing-requests.store'), [
        'name' => 'Jacksonville Fly-In',
        'description' => 'Some description.',
        'requested_at' => '',
    ]);

    $response->assertSessionHasErrors('requested_at');
    expect(StaffingRequest::count())->toBe(0);
});

// Managing staffing requests

test('given an events staff member, when visiting the management page, then submitted requests are shown', function () {
    $staff = User::factory()->create();
    $staff->assignRole(['staff', 'events']);

    $staffingRequest = StaffingRequest::factory()->create(['name' => 'Coastal Cruise']);

    $response = $this->actingAs($staff)->get(route('admin.staffing-requests.index'));

    $response->assertOk();
    $response->assertSee('Coastal Cruise');
    $response->assertSee($staffingRequest->user->name);
    $response->assertSee((string) $staffingRequest->id);
});

test('given a user without the staffing-requests:read permission, when visiting the management page, then the request is forbidden', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('view dashboard');

    $response = $this->actingAs($user)->get(route('admin.staffing-requests.index'));

    $response->assertForbidden();
});

test('given an events staff member, when viewing a staffing request, then the submitter details and description are shown', function () {
    $staff = User::factory()->create();
    $staff->assignRole(['staff', 'events']);

    $staffingRequest = StaffingRequest::factory()->create([
        'name' => 'Coastal Cruise',
        'description' => 'Please staff JAX_GND and JAX_TWR.',
    ]);

    $response = $this->actingAs($staff)->get(route('admin.staffing-requests.show', $staffingRequest));

    $response->assertOk();
    $response->assertSee('Coastal Cruise');
    $response->assertSee('Please staff JAX_GND and JAX_TWR.');
    $response->assertSee($staffingRequest->user->name);
    $response->assertSee($staffingRequest->user->email);
});

test('given an events staff member, when closing a staffing request, then it is kept and marked closed, and the submitter is emailed', function () {
    Mail::fake();

    $staff = User::factory()->create();
    $staff->assignRole(['staff', 'events']);

    $staffingRequest = StaffingRequest::factory()->create();

    $response = $this->actingAs($staff)->patch(route('admin.staffing-requests.close', $staffingRequest));

    $response->assertRedirect(route('admin.staffing-requests.index'));
    $response->assertSessionHas('success');

    expect(StaffingRequest::count())->toBe(1)
        ->and($staffingRequest->fresh()->closed)->toBeTrue();

    Mail::assertQueued(StaffingRequestClosed::class, fn ($mail) => $mail->hasTo($staffingRequest->user->email));
});

test('given an events staff member, when reopening a closed staffing request, then it is marked open again', function () {
    $staff = User::factory()->create();
    $staff->assignRole(['staff', 'events']);

    $staffingRequest = StaffingRequest::factory()->create(['closed' => true]);

    $response = $this->actingAs($staff)->patch(route('admin.staffing-requests.reopen', $staffingRequest));

    $response->assertRedirect(route('admin.staffing-requests.index'));
    $response->assertSessionHas('success');
    expect($staffingRequest->fresh()->closed)->toBeFalse();
});

test('given a user with only the staffing-requests:read permission, when closing a staffing request, then the request is forbidden and it stays open', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['view dashboard', 'staffing-requests:read']);

    $staffingRequest = StaffingRequest::factory()->create();

    $response = $this->actingAs($user)->patch(route('admin.staffing-requests.close', $staffingRequest));

    $response->assertForbidden();
    expect($staffingRequest->fresh()->closed)->toBeFalse();
});

test('given a user with only the staffing-requests:read permission, when visiting the management page, then requests are shown without the close action', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['view dashboard', 'staffing-requests:read']);

    StaffingRequest::factory()->create(['name' => 'Coastal Cruise']);

    $response = $this->actingAs($user)->get(route('admin.staffing-requests.index'));

    $response->assertOk();
    $response->assertSee('Coastal Cruise');
    $response->assertDontSee('name="_method" value="PATCH"', false);
});

test('given an events staff member, when visiting the management page, then the close action is available', function () {
    $staff = User::factory()->create();
    $staff->assignRole(['staff', 'events']);

    StaffingRequest::factory()->create(['name' => 'Coastal Cruise']);

    $response = $this->actingAs($staff)->get(route('admin.staffing-requests.index'));

    $response->assertOk();
    $response->assertSee('name="_method" value="PATCH"', false);
});

// Creating an event from a staffing request

test('given an events staff member, when creating an event from a staffing request, then the name and description are prefilled', function () {
    $staff = User::factory()->create();
    $staff->assignRole(['staff', 'events']);

    $staffingRequest = StaffingRequest::factory()->create([
        'name' => 'Coastal Cruise',
        'description' => 'Please staff JAX_GND and JAX_TWR.',
    ]);

    $response = $this->actingAs($staff)->get(route('admin.events.create', ['staffing_request' => $staffingRequest->id]));

    $response->assertOk();
    $response->assertSee('value="Coastal Cruise"', false);
    $response->assertSee('Please staff JAX_GND and JAX_TWR.');
});

test('given an events staff member, when opening the event form without a staffing request, then the fields are empty', function () {
    $staff = User::factory()->create();
    $staff->assignRole(['staff', 'events']);

    $response = $this->actingAs($staff)->get(route('admin.events.create'));

    $response->assertOk();
    $response->assertSee('value=""', false);
});

test('given a stale staffing request id, when opening the event form, then it still loads with empty fields', function () {
    $staff = User::factory()->create();
    $staff->assignRole(['staff', 'events']);

    $response = $this->actingAs($staff)->get(route('admin.events.create', ['staffing_request' => 999999]));

    $response->assertOk();
});

test('given an events staff member, when searching staffing requests, then only matching entries are shown', function () {
    $staff = User::factory()->create();
    $staff->assignRole(['staff', 'events']);

    StaffingRequest::factory()->create(['name' => 'Zuluair Arrival Bash']);
    StaffingRequest::factory()->create(['name' => 'Yankeetown Departure Rush']);

    $response = $this->actingAs($staff)->get(route('admin.staffing-requests.index', ['search' => 'Zuluair']));

    $response->assertOk();
    $response->assertSee('Zuluair Arrival Bash');
    $response->assertDontSee('Yankeetown Departure Rush');
});
