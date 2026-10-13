<?php

use App\Models\CertificationFacility;
use App\Models\CertificationLevel;
use App\Models\TrainingTicket;
use App\Models\User;
use App\Models\UserCertification;
use Database\Seeders\PermissionSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->seed(PermissionSeeder::class));

test('highestCertificationLevelFor returns the highest level held in a facility', function () {
    $facility = CertificationFacility::factory()->create();
    $ground = CertificationLevel::factory()->create(['facility_id' => $facility->id, 'level' => 1, 'abbreviation' => 'GND']);
    $tower = CertificationLevel::factory()->create(['facility_id' => $facility->id, 'level' => 2, 'abbreviation' => 'TWR']);

    $user = User::factory()->create();
    UserCertification::create(['user_id' => $user->id, 'certification_level_id' => $ground->id]);
    UserCertification::create(['user_id' => $user->id, 'certification_level_id' => $tower->id]);

    $user->load('certifications.certificationLevel');
    $highest = $user->highestCertificationLevelFor($facility->id);

    expect($highest->id)->toBe($tower->id);
    expect($highest->abbreviation)->toBe('TWR');
});

test('highestCertificationLevelFor returns null when the user is uncertified in a facility', function () {
    $facility = CertificationFacility::factory()->create();
    $user = User::factory()->create();

    expect($user->highestCertificationLevelFor($facility->id))->toBeNull();
});

test('hasCertificationLevel reflects held levels', function () {
    $level = CertificationLevel::factory()->create();
    $user = User::factory()->create();

    expect($user->hasCertificationLevel($level->id))->toBeFalse();

    UserCertification::create(['user_id' => $user->id, 'certification_level_id' => $level->id]);
    $user->load('certifications');

    expect($user->hasCertificationLevel($level->id))->toBeTrue();
});

test('certification facility identifier is uppercased on read', function () {
    $facility = CertificationFacility::factory()->create(['identifier' => 'zjx']);

    expect($facility->identifier)->toBe('ZJX');
});

test('UserCertification relates to its level and facility', function () {
    $facility = CertificationFacility::factory()->create();
    $level = CertificationLevel::factory()->create(['facility_id' => $facility->id]);
    $user = User::factory()->create();

    $cert = UserCertification::create(['user_id' => $user->id, 'certification_level_id' => $level->id]);

    expect($cert->certificationLevel->id)->toBe($level->id);
    expect($cert->facility->id)->toBe($facility->id);
});

test('certificationIssuedLabel formats the issued certification', function () {
    $facility = CertificationFacility::factory()->create(['identifier' => 'ZJX']);
    $level = CertificationLevel::factory()->create(['facility_id' => $facility->id, 'name' => 'Ground', 'abbreviation' => 'GND']);
    $user = User::factory()->create();
    $instructor = User::factory()->create();

    $ticket = TrainingTicket::create([
        'user_id' => $user->id,
        'instructor_id' => $instructor->id,
        'position' => 'MCO_APP',
        'session_start' => now()->format('Y-m-d H:i:s'),
        'session_end' => now()->addHour()->format('Y-m-d H:i:s'),
        'movements' => 10,
        'score' => 5,
        'notes' => 'Notes.',
        'location' => 1,
        'issued_certification_level_id' => $level->id,
    ]);

    expect($ticket->certificationIssuedLabel())->toBe('Certification Issued: ZJX Ground (GND)');
});

test('granting and revoking a certification writes audit log entries', function () {
    $facility = CertificationFacility::factory()->create(['identifier' => 'ZJX']);
    $level = CertificationLevel::factory()->create(['facility_id' => $facility->id, 'name' => 'Ground', 'abbreviation' => 'GND']);
    $user = User::factory()->create();

    $cert = UserCertification::create(['user_id' => $user->id, 'certification_level_id' => $level->id]);

    $issued = Activity::latest('id')->first();
    expect($issued->description)->toContain('Certification issued');
    expect($issued->subject_id)->toEqual($user->id);

    $cert->delete();

    $revoked = Activity::latest('id')->first();
    expect($revoked->description)->toContain('Certification revoked');
});

test('certificationIssuedLabel is null when no certification was issued', function () {
    $user = User::factory()->create();
    $instructor = User::factory()->create();

    $ticket = TrainingTicket::create([
        'user_id' => $user->id,
        'instructor_id' => $instructor->id,
        'position' => 'MCO_APP',
        'session_start' => now()->format('Y-m-d H:i:s'),
        'session_end' => now()->addHour()->format('Y-m-d H:i:s'),
        'movements' => 10,
        'score' => 5,
        'notes' => 'Notes.',
        'location' => 1,
    ]);

    expect($ticket->certificationIssuedLabel())->toBeNull();
});
