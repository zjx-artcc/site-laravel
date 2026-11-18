<?php

use App\Livewire\OiMatrix;
use App\Models\ReservedOperatingInitial;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function oiAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(['staff', 'admin']);

    return $user;
}

test('guests cannot view the oi matrix', function () {
    $this->get(route('admin.oi-matrix'))->assertForbidden();
});

test('staff without manage users cannot view the oi matrix', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $this->actingAs($staff)->get(route('admin.oi-matrix'))->assertForbidden();
});

test('admins can view the oi matrix', function () {
    $this->actingAs(oiAdmin())->get(route('admin.oi-matrix'))->assertOk()->assertSeeLivewire(OiMatrix::class);
});

test('the matrix renders all 676 combinations', function () {
    Livewire::actingAs(oiAdmin())
        ->test(OiMatrix::class)
        ->assertViewHas('cells', fn ($cells) => $cells->count() === 676
            && $cells->first()['initials'] === 'AA'
            && $cells->last()['initials'] === 'ZZ');
});

test('a vacant oi can be assigned to a rostered controller', function () {
    $controller = User::factory()->create(['operating_initials' => null]);

    Livewire::actingAs(oiAdmin())
        ->test(OiMatrix::class)
        ->call('select', 'ab')
        ->set('assignUserId', $controller->id)
        ->call('assign')
        ->assertHasNoErrors();

    expect($controller->fresh()->operating_initials)->toBe('AB');
});

test('an oi that is already in use cannot be assigned to someone else', function () {
    User::factory()->create(['operating_initials' => 'AB']);
    $other = User::factory()->create(['operating_initials' => null]);

    Livewire::actingAs(oiAdmin())
        ->test(OiMatrix::class)
        ->call('select', 'AB')
        ->set('assignUserId', $other->id)
        ->call('assign')
        ->assertHasErrors('assignUserId');

    expect($other->fresh()->operating_initials)->toBeNull();
});

test('an oi can be unassigned', function () {
    $holder = User::factory()->create(['operating_initials' => 'CD']);

    Livewire::actingAs(oiAdmin())
        ->test(OiMatrix::class)
        ->call('select', 'CD')
        ->call('unassign');

    expect($holder->fresh()->operating_initials)->toBeNull();
});

test('a vacant oi can be reserved with a reason and then cannot be assigned', function () {
    $admin = oiAdmin();
    $controller = User::factory()->create(['operating_initials' => null]);

    Livewire::actingAs($admin)
        ->test(OiMatrix::class)
        ->call('select', 'NA')
        ->set('reserveReason', 'In memory of Nicholas Ader')
        ->call('reserve')
        ->assertHasNoErrors()
        ->call('select', 'NA')
        ->set('assignUserId', $controller->id)
        ->call('assign')
        ->assertHasErrors('assignUserId');

    $reserved = ReservedOperatingInitial::find('NA');
    expect($reserved->reason)->toBe('In memory of Nicholas Ader')
        ->and($reserved->reserved_by)->toBe($admin->id)
        ->and($controller->fresh()->operating_initials)->toBeNull();
});

test('an oi in use cannot be reserved', function () {
    User::factory()->create(['operating_initials' => 'EF']);

    Livewire::actingAs(oiAdmin())
        ->test(OiMatrix::class)
        ->call('select', 'EF')
        ->call('reserve')
        ->assertHasErrors('reserveReason');

    expect(ReservedOperatingInitial::find('EF'))->toBeNull();
});

test('a reservation can be released', function () {
    ReservedOperatingInitial::create(['initials' => 'NA', 'reason' => 'In memory']);

    Livewire::actingAs(oiAdmin())
        ->test(OiMatrix::class)
        ->call('select', 'NA')
        ->call('release');

    expect(ReservedOperatingInitial::find('NA'))->toBeNull();
});

test('users without manage users cannot modify the matrix', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    Livewire::actingAs($staff)
        ->test(OiMatrix::class)
        ->call('select', 'NA')
        ->call('reserve')
        ->assertForbidden();

    expect(ReservedOperatingInitial::find('NA'))->toBeNull();
});

test('invalid initials cannot be selected', function () {
    Livewire::actingAs(oiAdmin())
        ->test(OiMatrix::class)
        ->call('select', '1!')
        ->assertSet('selected', null);
});

test('reserved oi is rejected when editing a user profile', function () {
    ReservedOperatingInitial::create(['initials' => 'NA']);
    $admin = oiAdmin();
    $target = User::factory()->create(['operating_initials' => null]);

    $this->actingAs($admin)
        ->put(route('users.update', ['user' => $target->id]), ['operatingInitials' => 'na'])
        ->assertSessionHasErrors('operatingInitials');

    expect($target->fresh()->operating_initials)->toBeNull();
});

test('clearing a users oi stores null rather than an empty string', function () {
    $user = User::factory()->create(['operating_initials' => 'GH']);
    $user->operating_initials = null;
    $user->save();

    expect($user->fresh()->getRawOriginal('operating_initials'))->toBeNull();
});

test('searching finds rostered controllers without an oi by name', function () {
    User::factory()->create(['first_name' => 'Jose', 'last_name' => 'Garcia', 'operating_initials' => null]);
    User::factory()->create(['first_name' => 'Jose', 'last_name' => 'Hasoi', 'operating_initials' => 'JH']);
    User::factory()->create(['first_name' => 'Jose', 'last_name' => 'Norost', 'operating_initials' => null, 'rostered' => false]);

    Livewire::actingAs(oiAdmin())
        ->test(OiMatrix::class)
        ->set('controllerSearch', 'jose')
        ->assertViewHas('controllerResults', fn ($r) => $r->count() === 1 && $r->first()->last_name === 'Garcia');
});

test('picking a controller then clicking a vacant oi assigns it', function () {
    $jose = User::factory()->create(['first_name' => 'Jose', 'last_name' => 'Garcia', 'operating_initials' => null]);

    Livewire::actingAs(oiAdmin())
        ->test(OiMatrix::class)
        ->call('pickController', $jose->id)
        ->assertSet('assigningUserId', $jose->id)
        ->assertViewHas('suggestion', 'JG')
        ->call('select', 'JG')
        ->assertSet('assigningUserId', null);

    expect($jose->fresh()->operating_initials)->toBe('JG');
});

test('clicking a reserved oi in controller mode does not assign it', function () {
    ReservedOperatingInitial::create(['initials' => 'NA']);
    $jose = User::factory()->create(['operating_initials' => null]);

    Livewire::actingAs(oiAdmin())
        ->test(OiMatrix::class)
        ->call('pickController', $jose->id)
        ->call('select', 'NA')
        ->assertSet('assigningUserId', $jose->id);

    expect($jose->fresh()->operating_initials)->toBeNull();
});

test('only managers can pick a controller', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $jose = User::factory()->create(['operating_initials' => null]);

    Livewire::actingAs($staff)->test(OiMatrix::class)->call('pickController', $jose->id)->assertForbidden();
});

test('the vacant oi panel searches controllers and assigns the chosen one', function () {
    $jose = User::factory()->create(['first_name' => 'Jose', 'last_name' => 'Garcia', 'operating_initials' => null]);
    User::factory()->create(['first_name' => 'Other', 'last_name' => 'Person', 'operating_initials' => null]);

    Livewire::actingAs(oiAdmin())
        ->test(OiMatrix::class)
        ->call('select', 'ZQ')
        ->set('panelSearch', 'jose')
        ->assertViewHas('panelResults', fn ($r) => $r->count() === 1 && $r->first()->is($jose))
        ->call('chooseForPanel', $jose->id)
        ->assertSet('assignUserId', $jose->id)
        ->call('assign')
        ->assertHasNoErrors();

    expect($jose->fresh()->operating_initials)->toBe('ZQ');
});

test('an oi cannot be assigned to a non-rostered user', function () {
    $gone = User::factory()->create(['rostered' => false, 'operating_initials' => null]);

    Livewire::actingAs(oiAdmin())
        ->test(OiMatrix::class)
        ->call('select', 'ZZ')
        ->set('assignUserId', $gone->id)
        ->call('assign')
        ->assertHasErrors('assignUserId');

    expect($gone->fresh()->operating_initials)->toBeNull();
});

test('assigning and unassigning an oi are audit logged with the admin as causer and the old and new values', function () {
    $admin = oiAdmin();
    $controller = User::factory()->create(['operating_initials' => null]);

    Livewire::actingAs($admin)
        ->test(OiMatrix::class)
        ->call('select', 'AB')
        ->set('assignUserId', $controller->id)
        ->call('assign')
        ->call('select', 'AB')
        ->call('unassign');

    $logs = Activity::where('subject_type', User::class)
        ->where('subject_id', $controller->id)
        ->where('event', 'updated')
        ->orderBy('id')
        ->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->causer_id)->toBe($admin->id)
        ->and($logs[0]->properties['attributes']['operating_initials'])->toBe('AB')
        ->and($logs[1]->causer_id)->toBe($admin->id)
        ->and($logs[1]->properties['old']['operating_initials'])->toBe('AB')
        ->and($logs[1]->properties['attributes']['operating_initials'])->toBeNull();
});

test('reserving and releasing an oi are audit logged in the standard shape', function () {
    $admin = oiAdmin();

    Livewire::actingAs($admin)
        ->test(OiMatrix::class)
        ->call('select', 'NA')
        ->set('reserveReason', 'In memory')
        ->call('reserve')
        ->call('select', 'NA')
        ->call('release');

    $created = Activity::where('subject_type', ReservedOperatingInitial::class)->where('event', 'created')->first();
    $deleted = Activity::where('subject_type', ReservedOperatingInitial::class)->where('event', 'deleted')->first();

    expect($created->causer_id)->toBe($admin->id)
        ->and($created->subject_id)->toBe('NA')
        ->and($created->properties['attributes'])->toBe(['initials' => 'NA', 'reason' => 'In memory'])
        ->and($deleted->causer_id)->toBe($admin->id)
        ->and($deleted->properties['old']['initials'])->toBe('NA');
});

test('reserving an already reserved oi is refused and does not overwrite or re-log it', function () {
    ReservedOperatingInitial::create(['initials' => 'NA', 'reason' => 'Original']);

    Livewire::actingAs(oiAdmin())
        ->test(OiMatrix::class)
        ->call('select', 'NA')
        ->set('reserveReason', 'Different')
        ->call('reserve')
        ->assertHasErrors('reserveReason');

    expect(ReservedOperatingInitial::find('NA')->reason)->toBe('Original')
        ->and(Activity::where('subject_type', ReservedOperatingInitial::class)->count())->toBe(0);
});
