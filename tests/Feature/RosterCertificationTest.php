<?php

use App\Models\CertificationFacility;
use App\Models\CertificationLevel;
use App\Models\User;
use App\Models\UserCertification;
use Database\Seeders\PermissionSeeder;

beforeEach(fn () => $this->seed(PermissionSeeder::class));

test('guests are redirected to login', function () {
    $this->get(route('roster.index'))->assertRedirect(route('login'));
});

test('the roster shows the highest held abbreviation and uncertified otherwise', function () {
    $facility = CertificationFacility::factory()->create(['identifier' => 'ZJX', 'order' => 1]);
    $ground = CertificationLevel::factory()->create(['facility_id' => $facility->id, 'level' => 1, 'abbreviation' => 'GND']);
    $tower = CertificationLevel::factory()->create(['facility_id' => $facility->id, 'level' => 2, 'abbreviation' => 'TWR']);

    $certified = User::factory()->create(['first_name' => 'Cert', 'last_name' => 'Ified']);
    User::factory()->create(['first_name' => 'Un', 'last_name' => 'Certified']);

    UserCertification::create(['user_id' => $certified->id, 'certification_level_id' => $ground->id]);
    UserCertification::create(['user_id' => $certified->id, 'certification_level_id' => $tower->id]);

    $response = $this->actingAs(User::factory()->create())->get(route('roster.index'));

    $response->assertStatus(200);

    // Scope assertions to the roster table itself, not the full page: the
    // page head contains random tokens (CSRF, Livewire IDs, asset hashes)
    // that can coincidentally contain "GND" and make a page-wide
    // assertDontSee flaky.
    preg_match('/<table.*<\/table>/s', $response->getContent(), $matches);
    $table = $matches[0] ?? '';

    expect($table)->toContain('TWR');        // highest level shown for the certified user
    expect($table)->not->toContain('GND');   // the lower level is not surfaced
    expect($table)->toContain('Uncertified');
});
