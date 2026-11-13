<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ManualContributor extends Model
{
    use LogsActivity;

    protected $fillable = ['github_username', 'display_name', 'section', 'note'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['github_username', 'display_name', 'section', 'note']);
    }
}
