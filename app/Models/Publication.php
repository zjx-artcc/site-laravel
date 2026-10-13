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

    public const DISK = 'public';

    // Upload extensions mapped to the MIME types their contents may sniff as; both halves must agree.
    public const ALLOWED_TYPES = [
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'json' => ['application/json'],
    ];

    // JSON is excluded: it is a vATIS profile to import, not something to read in a tab.
    private const INLINE_TYPES = ['application/pdf', 'image/png', 'image/jpeg'];

    private const FALLBACK_TYPE = 'application/octet-stream';

    private ?string $sniffedType = null;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'publication_category_id', 'original_filename'])
            ->logOnlyDirty();
    }

    protected $fillable = [
        'publication_category_id',
        'name',
        'description',
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
            ? route('publications.file', $this)
            : null);
    }

    /**
     * @return list<string>
     */
    public static function allowedMimeTypes(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::ALLOWED_TYPES))));
    }

    /**
     * Sniffed from the stored bytes, not the filename. Anything off the allowlist —
     * a legacy .docx, a missing file, contents that no longer match the extension —
     * falls back to generic binary, which downloads rather than renders.
     */
    public function servedMimeType(): string
    {
        return $this->sniffedType ??= $this->sniffStoredType();
    }

    /**
     * Drives both the Content-Disposition the file route sends and the action label
     * in the listing, so the two can never disagree about what clicking will do.
     */
    public function opensInBrowser(): bool
    {
        return in_array($this->servedMimeType(), self::INLINE_TYPES, true);
    }

    private function sniffStoredType(): string
    {
        if (! $this->file_path) {
            return self::FALLBACK_TYPE;
        }

        $detected = Storage::disk(self::DISK)->mimeType($this->file_path);

        return in_array($detected, self::allowedMimeTypes(), true)
            ? $detected
            : self::FALLBACK_TYPE;
    }
}
