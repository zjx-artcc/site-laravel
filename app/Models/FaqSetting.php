<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FaqSetting extends Model
{
    use LogsActivity;

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Default values used when a setting has never been saved.
     */
    public const DEFAULTS = [
        'faq_heading' => 'Frequently Asked Questions',
        'faq_intro' => 'New to vZJX or not on our Discord? Find answers to common questions about training, getting a mentor, and getting started below.',
    ];

    /**
     * Read a setting value, falling back to an explicit default or the built-in default.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = static::query()->where('key', $key)->value('value');

        return $value ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    /**
     * Create or update a setting value.
     */
    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['key', 'value']);
    }
}
