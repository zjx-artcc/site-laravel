<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class UserCertification extends Model
{
    protected $fillable = [
        'user_id',
        'certification_level_id',
    ];

    protected static function booted(): void
    {
        // Record certification grants/revokes in the audit log, attributed to the
        // affected user (subject) and the acting staff member (causer, resolved by
        // the activity logger from the authenticated user). DB-level cascade deletes
        // (e.g. removing a level/facility) bypass Eloquent events, so this only fires
        // for explicit user-initiated grants and revokes.
        static::created(fn (UserCertification $cert) => $cert->logChange('issued'));
        static::deleted(fn (UserCertification $cert) => $cert->logChange('revoked'));
    }

    protected function logChange(string $event): void
    {
        $level = $this->certificationLevel;
        $facility = $level?->facility;

        $descriptor = $level
            ? ': '.($facility ? $facility->identifier.' ' : '').$level->name.' ('.$level->abbreviation.')'
            : '';

        activity()
            ->performedOn($this->user)
            ->withProperties([
                'facility' => $facility?->identifier,
                'level' => $level?->name,
                'abbreviation' => $level?->abbreviation,
            ])
            ->log('Certification '.$event.$descriptor);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function certificationLevel(): BelongsTo
    {
        return $this->belongsTo(CertificationLevel::class, 'certification_level_id');
    }

    public function facility(): HasOneThrough
    {
        return $this->hasOneThrough(
            CertificationFacility::class,
            CertificationLevel::class,
            'id',           // CertificationLevel.id
            'id',           // CertificationFacility.id
            'certification_level_id', // UserCertification.certification_level_id
            'facility_id',  // CertificationLevel.facility_id
        );
    }
}
