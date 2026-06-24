<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ControllerMonthlyStat extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'year',
        'month',
        'delivery_hours',
        'ground_hours',
        'tower_hours',
        'approach_hours',
        'center_hours',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'delivery_hours' => 'float',
        'ground_hours' => 'float',
        'tower_hours' => 'float',
        'approach_hours' => 'float',
        'center_hours' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function totalHours(): float
    {
        return $this->delivery_hours
            + $this->ground_hours
            + $this->tower_hours
            + $this->approach_hours
            + $this->center_hours;
    }
}
