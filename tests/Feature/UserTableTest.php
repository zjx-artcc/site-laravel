<?php

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
