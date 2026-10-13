<?php

namespace App\Enums;

enum FeedbackExperience: string
{
    case OUTSTANDING = 'Outstanding';
    case VERY_GOOD = 'Very Good';
    case GOOD = 'Good';
    case OKAY = 'Okay';
    case POOR = 'Poor';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
