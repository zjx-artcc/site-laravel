<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EventPositionPreset extends Model
{
    use LogsActivity;

    protected $table = 'event_position_preset';

    protected $fillable = [
        'name',
        'positions',
    ];

    protected $casts = [
        'positions' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'positions']);
    }
}
