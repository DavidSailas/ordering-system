<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

/**
 * Who may open one order: the administrator any order, canteen staff the orders of their own
 * canteen, and a customer only the orders they placed.
 */
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Owner, UserRole::Cashier, UserRole::Runner => $user->canteen_id !== null && (int) $user->canteen_id === (int) $order->canteen_id,
            UserRole::Customer => $order->user_id !== null && $order->user_id === $user->id,
        };
    }
}
