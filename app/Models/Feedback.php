<?php

namespace App\Models;

use App\Enums\FeedbackExperience;
use App\Enums\FeedbackStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Feedback extends Model
{
    use HasFactory, Searchable;

    protected $table = 'feedback';

    protected $fillable = [
        'user_id',
        'controller_id',
        'position',
        'experience',
        'staff_followup',
        'comments',
        'status',
    ];

    protected $casts = [
        'staff_followup' => 'boolean',
        'experience' => FeedbackExperience::class,
        'status' => FeedbackStatus::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function controller()
    {
        return $this->belongsTo(User::class, 'controller_id');
    }

    public function staffComments()
    {
        return $this->hasMany(FeedbackComment::class)->orderBy('created_at');
    }

    public function visibleComments()
    {
        return $this->hasMany(FeedbackComment::class)->where('user_visible', true)->orderBy('created_at');
    }

    public function toSearchableArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'controller_id' => $this->controller_id,
            'submitter_name' => $this->user->name,
            'submitter_email' => $this->user->email,
            'controller_name' => $this->controller->name,
            'position' => $this->position,
            'experience' => $this->experience->value,
            'status' => $this->status->value,
        ];
    }
}
