<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Str;

class CertificationFacility extends Model
{
    use HasFactory;

    protected $fillable = [
        'identifier',
        'name',
        'order',
    ];

    protected function identifier(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => Str::upper($value),
            set: fn (mixed $value) => Str::upper($value),
        );
    }

    public function certificationLevels()
    {
        return $this->hasMany(CertificationLevel::class, 'facility_id')->orderBy('level', 'desc');
    }
}
