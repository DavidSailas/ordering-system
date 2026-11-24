<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Site-wide settings (key => value), e.g. the campus pass.
 *
 * @property string $key
 * @property string|null $value
 */
class SiteSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = static::query()->where('key', $key)->value('value');

        return $value ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
