<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\BlockedPhone;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Prank and no-show orders: the cashier (or owner / administrator) reports the order. It is cancelled
 * and its phone number can no longer place orders.
 */
class FakeOrderController extends Controller
{
    /** "Report fake order": cancel it and block the number. Only for orders nobody has paid yet. */
    public function report(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('view', $order);

        $message = DB::transaction(function () use ($order, $request): ?string {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->paid_at) {
                return 'Order #'.$locked->number.' is already paid, so it cannot be reported as fake. Use the refund option instead.';
            }

            if ($locked->status->canTransitionTo(OrderStatus::Canceled)) {
                $locked->update(['status' => OrderStatus::Canceled, 'canceled_at' => now()]);
            }

            BlockedPhone::block((string) $locked->phone, 'Fake order #'.$locked->number, $request->user());

            return null;
        });

        if ($message) {
            return back()->withErrors(['order' => $message]);
        }

        return back()->with('status', 'Order #'.$order->number.' was cancelled and '.($order->phone ?: 'its phone number').' can no longer order.');
    }

    /** Just block the number (orders that were already served, for the owner or administrator). */
    public function block(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('view', $order);

        if (! filled($order->phone)) {
            return back()->withErrors(['order' => 'This order has no phone number to block.']);
        }

        BlockedPhone::block((string) $order->phone, 'Blocked after order #'.$order->number, $request->user());

        return back()->with('status', $order->phone.' can no longer place orders.');
    }
}
