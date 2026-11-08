<?php

namespace App\Models\Concerns;

use App\Models\Canteen;
use App\Models\Scopes\CanteenScope;
use App\Support\CurrentCanteen;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCanteen
{
    public static function bootBelongsToCanteen(): void
    {
        static::addGlobalScope(new CanteenScope);

        static::creating(function ($model): void {
            if (! $model->canteen_id && ($id = CurrentCanteen::id())) {
                $model->canteen_id = $id;
            }
        });
    }

    /** @return BelongsTo<Canteen, $this> */
    public function canteen(): BelongsTo
    {
        return $this->belongsTo(Canteen::class);
    }
}
