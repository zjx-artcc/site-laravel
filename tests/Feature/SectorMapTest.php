<?php

use App\Livewire\SectorMap;
use Livewire\Livewire;

test('the sector map removes malformed palette values during Livewire updates', function () {
    Livewire::test(SectorMap::class)
        ->set('activeSectors', [5, ['invalid']])
        ->assertSet('activeSectors', [5])
        ->assertSee('ZJX 5');
});
