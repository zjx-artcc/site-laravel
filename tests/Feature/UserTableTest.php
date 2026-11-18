<?php

use App\Livewire\OiMatrix;
use App\Livewire\UserTable;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(PermissionSeeder::class));

test('the user table renders a row for a user with no joined_at date', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('manage users'); // unlocks the Joined / Last Activity columns
    $this->actingAs($viewer);

    $target = User::factory()->create([
        'first_name' => 'Nodate',
        'last_name' => 'Controller',
        'joined_at' => null,
    ]);

    Livewire::test(UserTable::class)
        ->assertOk()
        ->assertSee('Nodate')
        ->assertSee('—'); // placeholder shown instead of a formatted date
});

function userTableViewer(): User
{
    $viewer = User::factory()->create(['first_name' => 'Viewer', 'last_name' => 'Zzz']);
    $viewer->givePermissionTo('manage users');

    return $viewer;
}

test('the user table searches by name, cid and oi', function () {
    $this->actingAs(userTableViewer());
    $jose = User::factory()->create(['first_name' => 'Jose', 'last_name' => 'Garcia', 'operating_initials' => 'QX']);
    User::factory()->create(['first_name' => 'Other', 'last_name' => 'Person']);

    Livewire::test(UserTable::class)->set('search', 'jose')->assertSee('Garcia')->assertDontSee('Person');
    Livewire::test(UserTable::class)->set('search', (string) $jose->id)->assertSee('Garcia')->assertDontSee('Person');
    Livewire::test(UserTable::class)->set('search', 'qx')->assertSee('Garcia')->assertDontSee('Person');
});

test('the user table filters by roster status, home or visitor, and oi', function () {
    $this->actingAs(userTableViewer());
    User::factory()->create(['last_name' => 'Homeguy', 'facility' => config('app.vatusa_facility'), 'operating_initials' => 'HG']);
    User::factory()->create(['last_name' => 'Visitguy', 'facility' => 'ZZZ', 'operating_initials' => null]);
    User::factory()->create(['last_name' => 'Gonedude', 'rostered' => false]);

    Livewire::test(UserTable::class)->assertSee('Homeguy')->assertSee('Visitguy')->assertDontSee('Gonedude');
    Livewire::test(UserTable::class)->set('status', 'not-rostered')->assertSee('Gonedude')->assertDontSee('Homeguy');
    Livewire::test(UserTable::class)->set('membership', 'visitor')->assertSee('Visitguy')->assertDontSee('Homeguy');
    Livewire::test(UserTable::class)->set('membership', 'home')->assertSee('Homeguy')->assertDontSee('Visitguy');
    Livewire::test(UserTable::class)->set('oi', 'unassigned')->assertSee('Visitguy')->assertDontSee('Homeguy');
});

test('the user table sorts and ignores unknown sort fields', function () {
    $this->actingAs(userTableViewer());

    Livewire::test(UserTable::class)
        ->call('sortBy', 'rating')->assertSet('sortField', 'rating')->assertSet('sortDirection', 'asc')
        ->call('sortBy', 'rating')->assertSet('sortDirection', 'desc')
        ->call('sortBy', 'password')->assertSet('sortField', 'rating');
});

test('only managers see email and the assign oi link', function () {
    $staff = User::factory()->create(['last_name' => 'Staffer']);
    $staff->assignRole('staff');
    User::factory()->create(['last_name' => 'Needsoi', 'email' => 'needs@example.com', 'operating_initials' => null]);

    Livewire::actingAs($staff)->test(UserTable::class)->assertSee('Needsoi')->assertDontSee('needs@example.com')->assertDontSee('Assign OI');
    Livewire::actingAs(userTableViewer())->test(UserTable::class)->assertSee('needs@example.com')->assertSee('Assign OI');
});

test('the oi matrix honours a controller query parameter only for eligible controllers', function () {
    $admin = User::factory()->create();
    $admin->assignRole(['staff', 'admin']);
    $eligible = User::factory()->create(['operating_initials' => null]);
    $hasOi = User::factory()->create(['operating_initials' => 'ZY']);

    Livewire::withQueryParams(['controller' => $eligible->id])->actingAs($admin)
        ->test(OiMatrix::class)->assertSet('assigningUserId', $eligible->id);

    Livewire::withQueryParams(['controller' => $hasOi->id])->actingAs($admin)
        ->test(OiMatrix::class)->assertSet('assigningUserId', null);
});

test('non-managers cannot sort by the managers-only joined column', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    Livewire::actingAs($staff)->test(UserTable::class)
        ->call('sortBy', 'joined_at')
        ->assertSet('sortField', 'last_name');
});
