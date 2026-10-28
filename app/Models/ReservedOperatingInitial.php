<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservedOperatingInitial extends Model
{
    protected $primaryKey = 'initials';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['initials', 'reason', 'reserved_by'];

    protected function initials(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => strtoupper($value),
        );
    }

    public function reservedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reserved_by');
    }

    public static function isReserved(?string $initials): bool
    {
        return $initials !== null && static::whereKey(strtoupper($initials))->exists();
    }
}
