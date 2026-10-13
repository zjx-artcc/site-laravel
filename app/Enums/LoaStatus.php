<?php

namespace App\Enums;

enum LoaStatus: int
{
    case PENDING = 0;
    case APPROVED = 1;
    case DENIED = 2;
    case INACTIVE = 3;

    public function label(): string
    {
        return match ($this) {
            LoaStatus::PENDING => 'Pending',
            LoaStatus::APPROVED => 'Approved',
            LoaStatus::DENIED => 'Denied',
            LoaStatus::INACTIVE => 'Inactive',
        };
    }
}
