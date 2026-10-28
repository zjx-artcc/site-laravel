<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CertificationLevel extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'facility_id',
        'level',
        'name',
        'abbreviation',
    ];

    public function facility()
    {
        return $this->belongsTo(CertificationFacility::class, 'facility_id');
    }

    public function userCertifications()
    {
        return $this->hasMany(UserCertification::class, 'certification_level_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['facility_id', 'level', 'name', 'abbreviation']);
    }
}
