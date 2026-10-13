<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\DTOs\VatusaRosterUser;
use App\Enums\ControllerRating;
use Database\Factories\UserFactory;
use Http;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable, Searchable;

    private const PROFILE_IMAGE_DIRECTORY = 'profile/';

    private const DEFAULT_PROFILE_IMAGE = 'images/default_profile.jpg';

    /**
     * The primary key is the VATSIM CID, assigned explicitly rather than auto-incremented.
     */
    public $incrementing = false;

    protected $keyType = 'int';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'first_name',
        'last_name',
        'email',
        'rating',
        'joined_at',
        'division',
        'facility',
        'rostered',
        'discord_id',
        'operating_initials',
    ];

    /**
     * Authentication is exclusively via VATSIM Connect (OAuth). This application
     * stores no local passwords and there is no password column. The hidden
     * attributes below and the 'password' => 'hashed' cast are retained only as
     * standard-Laravel safety nets should password-based auth ever be introduced.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rating' => ControllerRating::class,
            'joined_at' => 'datetime',
            'rostered' => 'boolean',
        ];
    }

    protected static function booted()
    {
        static::created(function ($user) {
            $user->assignRole('core'); // default role
        });
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public static function updateFromVatusa(VatusaRosterUser $vatusaUser)
    {
        $user = static::upsert([
            'id' => $vatusaUser->cid,
            'first_name' => ucfirst($vatusaUser->firstName),
            // VATUSA always sends the real last name regardless of the privacy flag;
            // redaction to the CID happens locally and self-heals on the next sync
            // if the controller later disables name privacy.
            'last_name' => $vatusaUser->namePrivacy ? (string) $vatusaUser->cid : ucfirst($vatusaUser->lastName),
            'email' => $vatusaUser->email,
            'rating' => $vatusaUser->rating,
            'joined_at' => $vatusaUser->joinedFacility,
            'division' => 'USA',
            'facility' => $vatusaUser->facility,
            'rostered' => true,
            'discord_id' => $vatusaUser->discordId,
        ],
            ['id']);

        return $user;
    }

    protected function operatingInitials(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => strtoupper($value),
            set: fn ($value) => strtoupper($value)
        );
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => ucfirst($attributes['first_name']).' '.ucfirst($attributes['last_name'])
        );
    }

    protected function nameReversed(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => ucfirst($attributes['last_name'].', '.ucfirst($attributes['first_name']))
        );
    }

    /**
     * Return the configured public-disk URL for an uploaded profile image.
     *
     * Older records stored a URL-like `storage/profile/...` value; accept that
     * representation while keeping the database value as a disk-relative path
     * for all new uploads.
     */
    protected function profileImageUrl(): Attribute
    {
        return Attribute::get(function (): string {
            $path = $this->profileImageStoragePath();

            return $path
                ? Storage::disk('public')->url($path)
                : asset(self::DEFAULT_PROFILE_IMAGE);
        });
    }

    /**
     * Get a validated public-disk path for this user's uploaded profile image.
     */
    public function profileImageStoragePath(): ?string
    {
        if (blank($this->profile_image_route)) {
            return null;
        }

        $path = ltrim($this->profile_image_route, '/');
        $path = str_starts_with($path, 'storage/')
            ? substr($path, strlen('storage/'))
            : $path;

        return str_starts_with($path, self::PROFILE_IMAGE_DIRECTORY) ? $path : null;
    }

    protected function firstName(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => ucfirst($value),
            set: fn (string $value) => ucfirst($value),
        );
    }

    protected function lastName(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => ucfirst($value),
            set: fn (string $value) => ucfirst($value),
        );
    }

    public function staffRoles(): HasMany
    {
        return $this->hasMany(Staff::class, 'user_id');
    }

    public function trainingAssignmentsAsStudent(): HasMany
    {
        return $this->hasMany(TrainingAssignment::class, 'user_id')->orderBy('created_at', 'desc');
    }

    public function trainingAssignmentsAsInstructor(): HasMany
    {
        return $this->hasMany(TrainingAssignment::class, 'instructor_id');
    }

    public function trainingTicketsAsStudent(): HasMany
    {
        return $this->hasMany(TrainingTicket::class, 'user_id')->orderBy('created_at', 'desc');
    }

    public function trainingTicketsAsInstructor(): HasMany
    {
        return $this->hasMany(TrainingTicket::class, 'instructor_id');
    }

    public function soloCerts(): HasMany
    {
        return $this->hasMany(SoloCert::class, 'user_id')->orderBy('created_at', 'desc');
    }

    public function visitRequests(): HasMany
    {
        return $this->hasMany(VisitorRequest::class, 'user_id')->orderBy('created_at', 'desc');
    }

    public function loas(): HasMany
    {
        return $this->hasMany(Loa::class, 'user_id')->orderBy('created_at', 'desc');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['rating', 'email', 'first_name', 'last_name', 'id', 'operating_initials']);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(UserCertification::class, 'user_id');
    }

    /**
     * The highest certification level (by `level` tier) this user holds in the
     * given facility, or null if uncertified there. Operates on the loaded
     * `certifications.certificationLevel` collection to avoid per-cell queries
     * when the relation is eager-loaded (e.g. on the roster).
     */
    public function highestCertificationLevelFor(int $facilityId): ?CertificationLevel
    {
        return $this->certifications
            ->map(fn (UserCertification $c) => $c->certificationLevel)
            ->filter(fn (?CertificationLevel $level) => $level && $level->facility_id === $facilityId)
            ->sortByDesc('level')
            ->first();
    }

    public function hasCertificationLevel(int $certificationLevelId): bool
    {
        return $this->certifications
            ->contains('certification_level_id', $certificationLevelId);
    }

    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'id' => $this->id,
            'rating' => $this->rating->mapToString(),
            'facility' => $this->facility,
        ];
    }

    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_positions')
            ->withPivot('requested_position', 'assigned_position', 'start', 'end', 'assigned_start', 'assigned_end', 'notes', 'position_status')
            ->withTimestamps();
    }

    public static function createFromVatusa(int $id)
    {
        $userData = Http::retry(2, 500)->timeout(20)->get(config('app.vatusa_api_url').'/v2/user/'.$id, [
            'apikey' => config('app.vatusa_api_key'),
        ])->throw()->json()['data'] ?? throw new \Exception('Failed to fetch user data for CID '.$id);

        $vatusaUser = new VatusaRosterUser($userData);
        self::updateFromVatusa($vatusaUser);
    }
}
