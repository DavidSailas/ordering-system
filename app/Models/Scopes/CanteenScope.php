<?php

namespace App\Models\Scopes;

use App\Support\CurrentCanteen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Keeps one canteen's data away from the other: while a request belongs to a
 * canteen (a staff member's own canteen, or the canteen a customer picked),
 * every query on a scoped model is limited to it automatically.
 */
class CanteenScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $canteenId = CurrentCanteen::id();

        if ($canteenId !== null) {
            $builder->where($model->getTable().'.canteen_id', $canteenId);
        }
    }
}
