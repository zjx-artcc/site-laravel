<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeaturedField extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_featured_field', 'featured_field_id', 'event_id')
            ->withTimestamps();
    }
}
