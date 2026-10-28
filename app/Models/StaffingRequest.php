<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StaffingRequest extends Model
{
    use HasFactory, LogsActivity, Searchable;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'requested_at',
        'closed',
    ];

    public function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'closed' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'submitter_name' => $this->user->name,
            'submitter_email' => $this->user->email,
            'name' => $this->name,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['user_id', 'name', 'description', 'requested_at', 'closed']);
    }
}
