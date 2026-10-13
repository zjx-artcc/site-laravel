<?php

use App\Livewire\CertificationManager;
use App\Livewire\UserCertifications;
use App\Models\CertificationLevel;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(PermissionSeeder::class));

test('bulk certification page is forbidden without certifications:write', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');
    $this->actingAs($user);

    $this->get(route('certifications.index'))->assertStatus(403);
});

test('bulk certification page is accessible to instructors', function () {
    $user = User::factory()->create();
    $user->assignRole('instructor');
    $this->actingAs($user);

    $this->get(route('certifications.index'))->assertStatus(200);
});

test('the bulk manager toggle grants then revokes a certification', function () {
    $this->actingAs(User::factory()->create()->givePermissionTo('certifications:write'));

    $level = CertificationLevel::factory()->create();
    $target = User::factory()->create();

    Livewire::test(CertificationManager::class)
        ->call('toggleLevel', $target->id, $level->id);
    $this->assertDatabaseHas('user_certifications', ['user_id' => $target->id, 'certification_level_id' => $level->id]);

    Livewire::test(CertificationManager::class)
        ->call('toggleLevel', $target->id, $level->id);
    $this->assertDatabaseMissing('user_certifications', ['user_id' => $target->id, 'certification_level_id' => $level->id]);
});

test('the bulk manager is forbidden without certifications:write', function () {
    $this->actingAs(User::factory()->create()); // core role only

    Livewire::test(CertificationManager::class)->assertStatus(403);
});

test('the per-user editor grants then revokes for the mounted user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin'); // has certifications:write
    $this->actingAs($admin);

    $target = User::factory()->create();
    $level = CertificationLevel::factory()->create();

    Livewire::test(UserCertifications::class, ['user' => $target])
        ->call('toggleLevel', $level->id);
    $this->assertDatabaseHas('user_certifications', ['user_id' => $target->id, 'certification_level_id' => $level->id]);

    Livewire::test(UserCertifications::class, ['user' => $target])
        ->call('toggleLevel', $level->id);
    $this->assertDatabaseMissing('user_certifications', ['user_id' => $target->id, 'certification_level_id' => $level->id]);
});

test('the per-user editor forbids toggling without permission', function () {
    $user = User::factory()->create();
    $user->assignRole('staff'); // no certifications:write
    $this->actingAs($user);

    $target = User::factory()->create();
    $level = CertificationLevel::factory()->create();

    Livewire::test(UserCertifications::class, ['user' => $target])
        ->call('toggleLevel', $level->id)
        ->assertStatus(403);

    $this->assertDatabaseMissing('user_certifications', ['user_id' => $target->id, 'certification_level_id' => $level->id]);
});
