<?php

use App\Enums\VisitRequestStatus;
use App\Models\ManualContributor;
use App\Models\SoloCert;
use App\Models\User;
use App\Models\VisitorRequest;
use Database\Seeders\PermissionSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

test('visitor request changes are recorded in the audit log', function () {
    $staff = User::factory()->create();
    $this->actingAs($staff);

    $visitRequest = VisitorRequest::create([
        'user_id' => User::factory()->create()->id,
        'user_note' => 'I would like to visit.',
        'status' => VisitRequestStatus::PENDING,
    ]);

    $visitRequest->status = VisitRequestStatus::APPROVED;
    $visitRequest->save();

    $entry = Activity::where('subject_type', VisitorRequest::class)
        ->where('event', 'updated')
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->causer_id)->toBe($staff->id);
    expect($entry->properties['attributes']['status'])->toBe(VisitRequestStatus::APPROVED->value);
});

test('solo cert issue and revoke are recorded in the audit log', function () {
    $instructor = User::factory()->create();
    $this->actingAs($instructor);

    $soloCert = SoloCert::create([
        'user_id' => User::factory()->create()->id,
        'issued_by_id' => $instructor->id,
        'position' => 'JAX_TWR',
    ]);

    expect(Activity::where('subject_type', SoloCert::class)->where('event', 'created')->exists())->toBeTrue();

    $soloCert->revoked = true;
    $soloCert->save();

    $revocation = Activity::where('subject_type', SoloCert::class)->where('event', 'updated')->first();

    expect($revocation)->not->toBeNull();
    expect($revocation->causer_id)->toBe($instructor->id);
    expect($revocation->properties['attributes']['revoked'])->toBeTrue();
});

test('contributor add and remove are recorded in the audit log', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $contributor = ManualContributor::create([
        'github_username' => 'octocat',
        'section' => 'contributor',
    ]);

    $contributor->delete();

    $removal = Activity::where('subject_type', ManualContributor::class)
        ->where('event', 'deleted')
        ->first();

    expect($removal)->not->toBeNull();
    expect($removal->causer_id)->toBe($admin->id);
    // Spatie stores the deleted row under `old`; the viewer merges both keys.
    expect($removal->properties['old']['github_username'])->toBe('octocat');
});
