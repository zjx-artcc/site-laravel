<?php

namespace App\Enums;

enum FeedbackStatus: string
{
    case PENDING = 'pending';
    case STASHED = 'stashed';
    case RELEASED = 'released';

    public function label(): string
    {
        return match ($this) {
            FeedbackStatus::PENDING => 'Pending',
            FeedbackStatus::STASHED => 'Stashed',
            FeedbackStatus::RELEASED => 'Released',
        };
    }
}
