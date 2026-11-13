<?php

namespace App\Models;

use App\Enums\VisitRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VisitorRequest extends Model
{
    use LogsActivity, Searchable;

    protected $fillable = [
        'user_id',
        'status',
        'reason',
        'admin_notes',
        'user_note',
    ];

    public function casts()
    {
        return [
            'status' => VisitRequestStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['user_id', 'status', 'reason', 'admin_notes']);
    }

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->user->name,
            'reason' => $this->reason,
        ];
    }
}
