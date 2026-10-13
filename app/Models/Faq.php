<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Faq extends Model
{
    use LogsActivity;

    protected $fillable = [
        'category',
        'question',
        'answer',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Only published FAQs.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Ordered for display: by category, then sort order, then question.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('category')->orderBy('sort_order')->orderBy('question');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['category', 'question', 'answer', 'sort_order', 'is_published']);
    }
}
