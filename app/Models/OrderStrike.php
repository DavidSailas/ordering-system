<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One missed order: an order that nobody paid for in time.
 *
 * @property string $phone_key
 * @property string $phone
 * @property string $reason
 */
class OrderStrike extends Model
{
    /** @var list<string> */
    protected $fillable = ['phone_key', 'phone', 'order_id', 'school_member_id', 'reason'];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withoutGlobalScopes();
    }
}
