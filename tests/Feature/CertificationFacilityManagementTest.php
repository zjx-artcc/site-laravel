<?php

use App\Livewire\CertificationFacilityManager;
use App\Livewire\CertificationLevelRow;
use App\Livewire\CertificationLevelsTable;
use App\Models\CertificationFacility;
use App\Models\CertificationLevel;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    // Default authenticated actor for the Livewire component tests. Page-level
    // tests below override this with actingAs() as needed.
    $this->actingAs(User::factory()->create()->givePermissionTo('certification-facilities:write'));
});

test('certification facilities page is forbidden without permission', function () {
    $user = User::factory()->create();
    $user->assignRole('staff'); // has 'view dashboard' but not 'certification-facilities:write'
    $this->actingAs($user);

    $this->get(route('certification-facilities.index'))->assertStatus(403);
});

test('certification facilities page loads with permission', function () {
    $user = User::factory()->create();
    $user->assignRole(['staff', 'facilities']);
    $this->actingAs($user);

    $this->get(route('certification-facilities.index'))->assertStatus(200);
});

test('a facility can be created via the livewire manager', function () {
    Livewire::test(CertificationFacilityManager::class)
        ->set('name', 'Jacksonville')
        ->set('identifier', 'ZJX')
        ->set('order', 1)
        ->call('createFacility')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('certification_facilities', [
        'identifier' => 'ZJX', 'name' => 'Jacksonville', 'order' => 1,
    ]);
});

test('a duplicate facility identifier is rejected', function () {
    CertificationFacility::factory()->create(['identifier' => 'ZJX']);

    Livewire::test(CertificationFacilityManager::class)
        ->set('name', 'Duplicate')
        ->set('identifier', 'ZJX')
        ->set('order', 0)
        ->call('createFacility')
        ->assertHasErrors(['identifier']);
});

test('a facility can be edited and deleted via the livewire manager', function () {
    $facility = CertificationFacility::factory()->create(['name' => 'Old', 'order' => 0]);

    Livewire::test(CertificationFacilityManager::class)
        ->call('startEdit', $facility->id)
        ->set('editName', 'Renamed')
        ->set('editOrder', 5)
        ->call('updateFacility')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('certification_facilities', ['id' => $facility->id, 'name' => 'Renamed', 'order' => 5]);

    Livewire::test(CertificationFacilityManager::class)
        ->call('deleteFacility', $facility->id);

    $this->assertDatabaseMissing('certification_facilities', ['id' => $facility->id]);
});

test('a certification level can be created via livewire', function () {
    $facility = CertificationFacility::factory()->create();

    Livewire::test(CertificationLevelsTable::class, ['facilityId' => $facility->id])
        ->set('newName', 'Ground')
        ->set('newAbbreviation', 'GND')
        ->set('newLevel', 1)
        ->call('createLevel')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('certification_levels', [
        'facility_id' => $facility->id, 'abbreviation' => 'GND', 'level' => 1,
    ]);
});

test('a duplicate level within a facility is rejected', function () {
    $facility = CertificationFacility::factory()->create();
    CertificationLevel::factory()->create(['facility_id' => $facility->id, 'level' => 1]);

    Livewire::test(CertificationLevelsTable::class, ['facilityId' => $facility->id])
        ->set('newName', 'Another')
        ->set('newAbbreviation', 'ANO')
        ->set('newLevel', 1)
        ->call('createLevel')
        ->assertHasErrors(['newLevel']);
});

test('a level row can be edited and deleted', function () {
    $level = CertificationLevel::factory()->create(['name' => 'Old', 'abbreviation' => 'OLD', 'level' => 1]);

    Livewire::test(CertificationLevelRow::class, ['certificationLevel' => $level])
        ->call('edit')
        ->set('name', 'New')
        ->set('abbreviation', 'NEW')
        ->set('level', 2)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('certification-level-saved');

    $this->assertDatabaseHas('certification_levels', ['id' => $level->id, 'name' => 'New', 'abbreviation' => 'NEW', 'level' => 2]);

    Livewire::test(CertificationLevelRow::class, ['certificationLevel' => $level->fresh()])
        ->call('delete')
        ->assertDispatched('certification-level-deleted');

    $this->assertDatabaseMissing('certification_levels', ['id' => $level->id]);
});

test('case-variant facility identifiers are treated as duplicates', function () {
    CertificationFacility::factory()->create(['identifier' => 'ZJX']);

    Livewire::test(CertificationFacilityManager::class)
        ->set('name', 'Lowercase attempt')
        ->set('identifier', 'zjx')
        ->set('order', 0)
        ->call('createFacility')
        ->assertHasErrors(['identifier']);
});

test('the facility manager is forbidden without certification-facilities:write', function () {
    $this->actingAs(User::factory()->create()); // core role only

    Livewire::test(CertificationFacilityManager::class)->assertStatus(403);
});

test('level management is forbidden without certification-facilities:write', function () {
    $facility = CertificationFacility::factory()->create();
    $this->actingAs(User::factory()->create()); // core role only

    Livewire::test(CertificationLevelsTable::class, ['facilityId' => $facility->id])->assertStatus(403);
});
