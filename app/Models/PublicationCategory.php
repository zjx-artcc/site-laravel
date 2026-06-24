<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PublicationCategory extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'description', 'display_order', 'show_in_nav'])
            ->logOnlyDirty();
    }

    public const NAV_CACHE_KEY = 'navbar.publication_categories';
    private const ORDER_PARK_OFFSET = 1000000;

    protected $fillable = [
        'title',
        'description',
        'display_order',
        'show_in_nav',
    ];

    protected $casts = [
        'show_in_nav' => 'boolean',
    ];

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public const MOBILE_NAV_CACHE_KEY = 'navbar.publication_categories.mobile';

    public static function forNavbar()
    {
        return Cache::rememberForever(self::NAV_CACHE_KEY, function () {
            return self::orderBy('display_order')
                ->orderBy('title')
                ->get(['id', 'title']);
        });
    }

    public static function forMobileNav()
    {
        return Cache::rememberForever(self::MOBILE_NAV_CACHE_KEY, function () {
            return self::where('show_in_nav', true)
                ->orderBy('display_order')
                ->orderBy('title')
                ->get(['id', 'title']);
        });
    }

    public static function repositionAndNormalize(int $id, int $targetPosition): void
    {
        DB::transaction(function () use ($id, $targetPosition) {
            $all = self::orderBy('display_order')->orderBy('id')->get();
            $moving = $all->firstWhere('id', $id);

            if (! $moving) {
                return;
            }

            $others = $all->reject(fn ($c) => $c->id === $id)->values();
            $target = max(0, min($targetPosition, $others->count()));

            $orderedIds = $others->pluck('id')->all();
            array_splice($orderedIds, $target, 0, [$id]);

            self::writeOrderedIds($orderedIds);
        });
    }

    public static function normalizeOrder(): void
    {
        DB::transaction(function () {
            $ordered = self::orderBy('display_order')->orderBy('id')->pluck('id')->all();

            $needsFix = false;
            foreach ($ordered as $i => $id) {
                $current = self::whereKey($id)->value('display_order');
                if ((int) $current !== $i) {
                    $needsFix = true;
                    break;
                }
            }

            if ($needsFix) {
                self::writeOrderedIds($ordered);
            }
        });
    }

    private static function writeOrderedIds(array $orderedIds): void
    {
        foreach ($orderedIds as $i => $id) {
            self::whereKey($id)->update(['display_order' => self::ORDER_PARK_OFFSET + $i]);
        }

        foreach ($orderedIds as $i => $id) {
            self::whereKey($id)->update(['display_order' => $i]);
        }

        Cache::forget(self::NAV_CACHE_KEY);
        Cache::forget(self::MOBILE_NAV_CACHE_KEY);
    }

    protected static function booted(): void
    {
        $flush = function () {
            Cache::forget(self::NAV_CACHE_KEY);
            Cache::forget(self::MOBILE_NAV_CACHE_KEY);
        };

        static::saved($flush);
        static::deleted($flush);
    }
}
