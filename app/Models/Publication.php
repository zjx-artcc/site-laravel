<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Publication extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'version', 'publication_category_id', 'original_filename'])
            ->logOnlyDirty();
    }

    protected $fillable = [
        'publication_category_id',
        'name',
        'description',
        'version',
        'file_path',
        'original_filename',
        'file_size',
    ];

    protected $appends = ['file_url'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(PublicationCategory::class, 'publication_category_id');
    }

    protected function fileUrl(): Attribute
    {
        return Attribute::get(fn () => $this->file_path
            ? Storage::disk('public')->url($this->file_path)
            : null);
    }

    public function isViewableInBrowser(): bool
    {
        $ext = strtolower(pathinfo($this->original_filename ?? $this->file_path, PATHINFO_EXTENSION));

        return in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'txt']);
    }
}
