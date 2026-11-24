<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;

/**
 * "Pay first, then we cook": a new order waits for payment and is cancelled by itself if nobody pays.
 * A prank order therefore never reaches the kitchen or a runner, and never leaves food to waste.
 *
 *  - No payment sent yet: cancelled after ORDER_UNPAID_EXPIRY_MINUTES (default 15).
 *  - Customer already sent a GCash / Maya reference: it waits longer (ORDER_UNPAID_REVIEW_MINUTES, default 60)
 *    so a cashier who is busy never cancels an order that was really paid.
 *
 * Each unpaid order nobody tried to pay for also counts as a missed order (see Strikes).
 *
 * Runs every minute from the scheduler, and also whenever a cashier board or "My orders" page opens,
 * so it keeps working even if the server's cron job is not set up.
 */
class UnpaidOrders
{
    public static function expire(): int
    {
        $plain = now()->subMinutes(max(1, (int) config('restaurant.unpaid_expiry_minutes', 15)));
        $submitted = now()->subMinutes(max(1, (int) config('restaurant.unpaid_review_minutes', 60)));

        $due = Order::withoutGlobalScopes()
            ->where('status', OrderStatus::AwaitingPayment->value)
            ->whereNull('paid_at')
            ->where(function ($query) use ($plain, $submitted): void {
                $query->where(fn ($q) => $q->whereNull('customer_payment_ref')->where('created_at', '<=', $plain))
                    ->orWhere(fn ($q) => $q->whereNotNull('customer_payment_ref')->where('created_at', '<=', $submitted));
            })
            ->get();

        $cancelled = 0;

        foreach ($due as $order) {
            // Checked again inside the update, so an order that was paid a moment ago is never cancelled.
            $changed = Order::withoutGlobalScopes()->whereKey($order->id)
                ->where('status', OrderStatus::AwaitingPayment->value)
                ->whereNull('paid_at')
                ->update(['status' => OrderStatus::Canceled->value, 'canceled_at' => now()]);

            if ($changed === 0) {
                continue;
            }

            $cancelled++;

            // Nobody even tried to pay: that is a missed order. (A customer who sent a payment reference is never penalised.)
            if (blank($order->customer_payment_ref)) {
                Strikes::record($order, 'Order #'.$order->number.' was not paid in time');
            }
        }

        return $cancelled;
    }

    /** Same as expire(), but at most once every 30 seconds however many pages ask. */
    public static function expireThrottled(): void
    {
        if (Cache::add('orders:expire-unpaid-lock', 1, 30)) {
            static::expire();
        }
    }
}
