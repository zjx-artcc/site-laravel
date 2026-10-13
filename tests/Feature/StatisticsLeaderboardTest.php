<?php

use App\Models\ControllerMonthlyStat;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

test('guests are redirected to login', function () {
    $this->get(route('statistics.index', ['year' => 2025, 'month' => 'all']))->assertRedirect(route('login'));
});

test('all-months leaderboard sums each controllers hours in SQL', function () {
    $this->actingAs(User::factory()->create());

    $user = User::factory()->create(['rostered' => true, 'rating' => 5]);

    ControllerMonthlyStat::create([
        'user_id' => $user->id, 'year' => 2025, 'month' => 1,
        'delivery_hours' => 1, 'ground_hours' => 2, 'tower_hours' => 0,
        'approach_hours' => 0, 'center_hours' => 0,
    ]);
    ControllerMonthlyStat::create([
        'user_id' => $user->id, 'year' => 2025, 'month' => 2,
        'delivery_hours' => 3, 'ground_hours' => 0, 'tower_hours' => 0,
        'approach_hours' => 0, 'center_hours' => 0,
    ]);

    $response = $this->get(route('statistics.index', ['year' => 2025, 'month' => 'all']))
        ->assertOk()
        ->assertSee('images/default_profile.jpg'); // avatar rendered for the controller

    $stats = $response->viewData('stats');

    $row = $stats->firstWhere('user_id', $user->id);

    expect($row)->not->toBeNull()
        ->and($row->delivery_hours)->toBe(4.0)
        ->and($row->ground_hours)->toBe(2.0)
        ->and($row->totalHours())->toBe(6.0);

    // One aggregated row per controller, not one row per monthly record.
    expect($stats->where('user_id', $user->id))->toHaveCount(1);
});

test('the all-time label uses the earliest year and month that actually occurred together', function () {
    $this->actingAs(User::factory()->create());

    $user = User::factory()->create(['rostered' => true, 'rating' => 5]);

    foreach ([[2025, 6], [2025, 11], [2026, 1], [2026, 8]] as [$year, $month]) {
        ControllerMonthlyStat::create([
            'user_id' => $user->id, 'year' => $year, 'month' => $month,
            'delivery_hours' => 1, 'ground_hours' => 0, 'tower_hours' => 0,
            'approach_hours' => 0, 'center_hours' => 0,
        ]);
    }

    $response = $this->get(route('statistics.index'))->assertOk();

    expect($response->viewData('allTimeSince'))->toBe('Jun 2025');
    $response->assertSee('Total ARTCC Hours since Jun 2025');
});

test('given no statistics at all, when viewing the page, then there is no since date to label', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('statistics.index'))->assertOk();

    expect($response->viewData('allTimeSince'))->toBeNull();
});
