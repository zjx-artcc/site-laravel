<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EventPosition extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'requested_position',
        'assigned_position',
        'start',
        'end',
        'assigned_start',
        'assigned_end',
        'notes',
        'position_status',
        'notified_at',
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'assigned_start' => 'datetime',
        'assigned_end' => 'datetime',
        'notified_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function events()
    {
        return $this->belongsToMany(Event::class);
    }

    /**
     * Assigned positions for this event that aren't in $keepPositions (case-insensitive).
     * Used to refuse dropping a position that someone is already working.
     *
     * @return Collection<int, string>
     */
    public static function assignedPositionsOutsideOf(int $eventId, array $keepPositions): Collection
    {
        $keep = array_map('strtoupper', $keepPositions);

        return static::where('event_id', $eventId)
            ->whereNotNull('assigned_position')
            ->pluck('assigned_position')
            ->unique(fn ($position) => strtoupper($position))
            ->reject(fn ($position) => in_array(strtoupper($position), $keep, true))
            ->values();
    }
}
