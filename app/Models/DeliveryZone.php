<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCanteen;
use Database\Factories\DeliveryZoneFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One building a runner delivers to, e.g. "Annex" with 10 floors.
 *
 * Fee = base fee + extra per floor of travel. Inside the canteen's own building the travel is counted
 * from the canteen's floor (up or down); anywhere else it is counted from the ground floor.
 *
 * @property int $id
 * @property int $canteen_id
 * @property string $name
 * @property int $floors
 * @property numeric-string $base_fee
 * @property numeric-string $per_floor_fee
 * @property bool $is_home
 * @property int|null $home_floor
 * @property bool $is_active
 */
class DeliveryZone extends Model
{
    /** @use HasFactory<DeliveryZoneFactory> */
    use BelongsToCanteen, HasFactory;

    /** No delivery may ever cost more than this (pesos), whatever the numbers typed in say. */
    public const MAX_FEE = 1000;

    /** @var list<string> */
    protected $fillable = ['canteen_id', 'name', 'floors', 'base_fee', 'per_floor_fee', 'is_home', 'home_floor', 'is_active', 'sort_order'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'floors' => 'integer',
            'base_fee' => 'decimal:2',
            'per_floor_fee' => 'decimal:2',
            'is_home' => 'boolean',
            'home_floor' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @param Builder<DeliveryZone> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function acceptsFloor(int $floor): bool
    {
        return $floor >= 1 && $floor <= $this->floors;
    }

    /** How many floors the runner travels to reach this floor. */
    public function floorsTravelled(int $floor): int
    {
        if ($this->is_home && $this->home_floor) {
            return abs($floor - $this->home_floor);
        }

        return max(0, $floor - 1);
    }

    /** The delivery fee for a floor, in centavos (whole numbers, so money never drifts). */
    public function feeCentsForFloor(int $floor): int
    {
        $cents = (int) round((float) $this->base_fee * 100) + $this->floorsTravelled($floor) * (int) round((float) $this->per_floor_fee * 100);

        return min($cents, self::MAX_FEE * 100);
    }

    /** The checkout page shows this table, so the fee a customer sees always comes from the server's own maths. */
    public function feeTable(): array
    {
        $table = [];

        for ($floor = 1; $floor <= $this->floors; $floor++) {
            $table[$floor] = $this->feeCentsForFloor($floor) / 100;
        }

        return $table;
    }

    /** Starting buildings for a brand-new canteen (the admin edits them afterwards). */
    public static function seedDefaultsFor(Canteen $canteen): void
    {
        if (static::withoutGlobalScopes()->where('canteen_id', $canteen->id)->exists()) {
            return;
        }

        static::withoutGlobalScopes()->insert([
            ['canteen_id' => $canteen->id, 'name' => 'Annex', 'floors' => 10, 'base_fee' => 10, 'per_floor_fee' => 2, 'is_home' => true, 'home_floor' => 6, 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['canteen_id' => $canteen->id, 'name' => 'Main Building', 'floors' => 6, 'base_fee' => 25, 'per_floor_fee' => 2, 'is_home' => false, 'home_floor' => null, 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
