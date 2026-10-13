<?php

use App\Models\EventPositionPreset;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $staff = User::factory()->create();
    $staff->assignRole('staff', 'events');
    $this->actingAs($staff);
});

test('store normalizes messy comma input', function () {
    $this->post(route('admin.events.position-presets.store'), [
        'name' => 'Messy',
        'positions' => ' jax_ctr ,MCO_APP, mco_app,,',
    ])->assertRedirect(route('admin.events.position-presets.index'));

    expect(EventPositionPreset::where('name', 'Messy')->first()->positions)
        ->toBe(['JAX_CTR', 'MCO_APP']);
});

test('update normalizes messy comma input the same way as store', function () {
    $preset = EventPositionPreset::create(['name' => 'Messy', 'positions' => ['JAX_CTR']]);

    $this->put(route('admin.events.position-presets.update', ['position_preset' => $preset->id]), [
        'name' => 'Messy',
        'positions' => ' jax_ctr ,MCO_APP, mco_app,,',
    ])->assertRedirect(route('admin.events.position-presets.index'));

    expect($preset->fresh()->positions)->toBe(['JAX_CTR', 'MCO_APP']);
});
