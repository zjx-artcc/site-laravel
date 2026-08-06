<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SoloCert extends Model
{
    use LogsActivity, Searchable;

    protected $fillable = [
        'user_id',
        'issued_by_id',
        'position',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'revoked' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_id');
    }

    public function expires(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->created_at->addDays(30),
        );
    }

    public function expired(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->expires->isBefore(now()),
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['user_id', 'issued_by_id', 'position', 'revoked']);
    }

    public function toSearchableArray(): array
    {
        return [
            'user' => $this->user->name,
            'position' => $this->position,
            'issued_by_id' => $this->issuedBy?->name,
        ];
    }
}
