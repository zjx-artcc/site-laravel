<?php

namespace App\Enums;

enum TrainingStatus: int
{
    case ACTIVE = 1;
    case SOLO = 2;
    case MOCK = 3;
    case CHECKOUT = 4;
    case COMPLETE = 5;
    case FORFEIT = 6;

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::SOLO => 'Solo Cert',
            self::MOCK => 'Mock OTS',
            self::CHECKOUT => 'Checkout',
            self::COMPLETE => 'Complete',
            self::FORFEIT => 'Forfeit',
        };
    }

    /**
     * daisyUI badge classes used to render this status as a colored chip.
     *
     * The palette reads as a progression: green while training is live, cool
     * tones through the intermediate stages, brand blue once complete, red on
     * forfeit. Green is reserved for ACTIVE so it never competes with COMPLETE.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::ACTIVE => 'badge-success text-success-content',
            self::SOLO => 'badge-info text-info-content',
            self::MOCK => 'badge-warning text-warning-content',
            self::CHECKOUT => 'badge-secondary text-secondary-content',
            self::COMPLETE => 'badge-primary text-primary-content',
            self::FORFEIT => 'badge-error text-error-content',
        };
    }

    public static function fromValue(int $value): ?self
    {
        return self::tryFrom($value);
    }
}
