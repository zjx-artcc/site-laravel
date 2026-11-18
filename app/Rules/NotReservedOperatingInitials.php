<?php

namespace App\Rules;

use App\Models\ReservedOperatingInitial;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotReservedOperatingInitials implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ReservedOperatingInitial::isReserved($value)) {
            $fail('These operating initials are reserved and cannot be assigned.');
        }
    }
}
