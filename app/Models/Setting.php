<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Simple key/value store for the canteen settings the admin can edit.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Save several settings at once. A null value removes the row so the
     * default from config/restaurant.php applies again.
     *
     * @param  array<string, string|null>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value === null) {
                static::query()->where('key', $key)->delete();

                continue;
            }

            static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    /**
     * Copy the saved settings over config('restaurant.*').
     * Called on every request from AppServiceProvider; it must never break
     * the site, so a missing table (before `migrate`) is silently ignored.
     */
    public static function applyToConfig(): void
    {
        try {
            $s = static::query()->pluck('value', 'key');
        } catch (\Throwable) {
            return;
        }

        $map = [
            'canteen_name' => 'restaurant.name',
            'address' => 'restaurant.address',
            'phone' => 'restaurant.phone',
            'receipt_footer' => 'restaurant.receipt_footer',
            'currency' => 'restaurant.currency',
            'logo_path' => 'restaurant.logo_path',
            'gcash_number' => 'restaurant.payments.gcash.number',
            'gcash_name' => 'restaurant.payments.gcash.name',
            'maya_number' => 'restaurant.payments.maya.number',
            'maya_name' => 'restaurant.payments.maya.name',
        ];

        foreach ($map as $key => $configKey) {
            if (filled($s[$key] ?? null)) {
                config([$configKey => $s[$key]]);
            }
        }

        if (isset($s['accepting_orders'])) {
            config(['restaurant.accepting_orders' => $s['accepting_orders'] === '1']);
        }

        if (isset($s['buildings'])) {
            config(['restaurant.buildings' => static::splitLines($s['buildings'])]);
        }
    }

    /** @return list<string> */
    public static function splitLines(?string $text): array
    {
        return collect(preg_split('/\R/', (string) $text) ?: [])
            ->map(fn ($line) => trim($line))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
