<?php

// https://vatusa-api.ztlartcc.org/#tag/training/paths/~1user~1%7Bcid%7D~1training~1record/post

namespace App\Models;

use DateTime;
use Exception;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TrainingTicket extends Model
{
    use LogsActivity;
    use Searchable;

    /**
     * Site-only location for a session the student didn't attend. VATUSA only
     * accepts 0-2, so these are synced as Classroom with a "No Show" note.
     */
    public const LOCATION_NO_SHOW = 3;

    public const LOCATIONS = [
        0 => 'Classroom',
        1 => 'Live Network',
        2 => 'Sweatbox',
        self::LOCATION_NO_SHOW => 'No Show',
    ];

    public $fillable = [
        'user_id',
        'instructor_id',
        'movements',
        'score',
        'notes',
        'instructor_notes',
        'location',
        'vatusa_id',
        'vatusa_synced',
        'position',
        'issued_certification_level_id',
        'session_start',
        'session_end',
    ];

    public function student()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }

    public function instructor()
    {
        return $this->belongsTo('App\Models\User', 'instructor_id');
    }

    public function issuedCertificationLevel()
    {
        return $this->belongsTo(CertificationLevel::class, 'issued_certification_level_id');
    }

    /**
     * A human-readable label for the certification issued alongside this ticket,
     * e.g. "Certification Issued: ZJX Ground (GND)", or null if none was issued.
     * Reused by both the VATUSA sync payload and the notification email so the
     * wording stays in one place.
     */
    public function certificationIssuedLabel(): ?string
    {
        $level = $this->issuedCertificationLevel;

        if (! $level) {
            return null;
        }

        $facility = $level->facility;

        return 'Certification Issued: '
            .($facility ? $facility->identifier.' ' : '')
            .$level->name
            .' ('.$level->abbreviation.')';
    }

    public function isNoShow(): bool
    {
        return (int) $this->location === self::LOCATION_NO_SHOW;
    }

    public function locationLabel(): string
    {
        return self::LOCATIONS[$this->location] ?? 'Unknown';
    }

    public function duration(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                try {
                    $startDate = new DateTime($attributes['session_start']);
                    $endDate = new DateTime($attributes['session_end']);
                    $duration = $startDate->diff($endDate);
                } catch (Exception $e) {
                    return '00:00';
                }

                return $duration->format('%H:%I');
            }
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['user_id', 'instructor_id', 'duration', 'movements', 'score', 'notes', 'instructor_notes', 'location']);
    }

    public function toSearchableArray(): array
    {
        return [
            'user_id' => $this->student->id,
            'instructor_id' => $this->instructor->id,
            'student_name' => $this->student->name,
            'instructor_name' => $this->instructor->name,
            'position' => $this->position,
            'date' => $this->session_start,
        ];
    }
}
