<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One dish (and how many of it) in a customer's saved cart. */
class CartItem extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'guest_token', 'menu_item_id', 'quantity'];

    /** @return BelongsTo<MenuItem, $this> */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
