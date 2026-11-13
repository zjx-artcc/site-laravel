<?php

namespace App\View\Components;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\View\Component;

class ZuluTime extends Component
{
    public ?Carbon $time;

    /**
     * Renders a time in UTC on a 24-hour clock with a "Z" suffix, e.g. "Oct 28, 2026 18:30Z".
     * Pass a custom PHP date $format to change the date part, e.g. format="H:i" for time only.
     */
    public function __construct(DateTimeInterface|string|null $time, public string $format = 'M j, Y H:i')
    {
        $this->time = $time ? Carbon::parse($time)->utc() : null;
    }

    public function render(): View|Closure|string
    {
        return view('components.zulu-time');
    }
}
