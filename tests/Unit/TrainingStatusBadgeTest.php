<?php

use App\Enums\TrainingStatus;

test('every training status maps to a distinct daisyUI badge color', function () {
    $classes = collect(TrainingStatus::cases())->map->badgeClass();

    expect($classes)->toHaveCount(count(TrainingStatus::cases()));
    expect($classes->unique())->toHaveCount($classes->count());
});

test('the live and terminal statuses use the expected colors', function () {
    expect(TrainingStatus::ACTIVE->badgeClass())->toContain('badge-success');
    expect(TrainingStatus::FORFEIT->badgeClass())->toContain('badge-error');
    expect(TrainingStatus::COMPLETE->badgeClass())->toContain('badge-primary');
});

test('green is reserved for the active status', function () {
    $green = collect(TrainingStatus::cases())
        ->filter(fn (TrainingStatus $status) => str_contains($status->badgeClass(), 'badge-success'));

    expect($green->values()->all())->toBe([TrainingStatus::ACTIVE]);
});
