<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CenterSector extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'split',
        'sector_id',
        'active_sector_id',
    ];

    protected $casts = [
        'sector_id' => 'integer',
        'active_sector_id' => 'integer',
    ];
}
