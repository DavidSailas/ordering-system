<?php

namespace App\Models;

use Database\Factories\CanteenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One canteen = one owner's shop. Its dishes, categories, orders, staff,
 * logo, colour and payment accounts never mix with another canteen's.
 */
class Canteen extends Model
{
    /** @use HasFactory<CanteenFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name', 'slug', 'tagline', 'accent', 'logo_path', 'is_open', 'phone', 'address',
        'receipt_footer', 'buildings', 'gcash_number', 'gcash_name', 'maya_number', 'maya_name', 'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_open' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** The oldest canteen (used by tests and single-canteen installs). */
    public static function default(): static
    {
        return static::query()->orderBy('id')->first() ?? static::factory()->create();
    }

    /** @return HasMany<User, $this> */
    public function staff(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return list<string> */
    public function buildingList(): array
    {
        return collect(preg_split('/\R/', (string) $this->buildings) ?: [])
            ->map(fn ($line) => trim($line))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** Make config('restaurant.*') describe this canteen (name, logo, payments, buildings, open/closed). */
    public function applyToConfig(): void
    {
        config([
            'restaurant.canteen_id' => $this->id,
            'restaurant.name' => $this->name,
            'restaurant.tagline' => $this->tagline,
            'restaurant.logo_path' => $this->logo_path,
            'restaurant.accepting_orders' => (bool) $this->is_open,
            'restaurant.buildings' => $this->buildingList(),
            'restaurant.payments.gcash.number' => $this->gcash_number,
            'restaurant.payments.gcash.name' => $this->gcash_name ?: $this->name,
            'restaurant.payments.maya.number' => $this->maya_number,
            'restaurant.payments.maya.name' => $this->maya_name ?: $this->name,
        ]);

        foreach (['address', 'phone', 'receipt_footer'] as $key) {
            if (filled($this->{$key})) {
                config(["restaurant.$key" => $this->{$key}]);
            }
        }
    }

    /** CSS custom properties that recolour the whole UI in this canteen's accent colour. */
    public function accentCss(): string
    {
        $hex = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $this->accent) ? $this->accent : '#e25806';
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        $mix = function (array $target, float $amount) use ($r, $g, $b): string {
            return sprintf(
                '#%02x%02x%02x',
                (int) round($r + ($target[0] - $r) * $amount),
                (int) round($g + ($target[1] - $g) * $amount),
                (int) round($b + ($target[2] - $b) * $amount),
            );
        };

        $black = [0, 0, 0];
        $white = [255, 255, 255];

        $vars = [
            'brand' => $hex,
            'brand-600' => $mix($black, 0.12),
            'brand-700' => $mix($black, 0.3),
            'brand-50' => $mix($white, 0.93),
            'brand-100' => $mix($white, 0.84),
            'brand-200' => $mix($white, 0.66),
        ];
        $vars['brand-dark'] = $vars['brand-700'];
        $vars['brand-tint'] = $vars['brand-50'];
        $vars['brand-line'] = $vars['brand-100'];

        return collect($vars)->map(fn ($value, $name) => "--$name:$value;")->implode('');
    }
}
