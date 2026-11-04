<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\StorePayments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StorePaymentController extends Controller
{
    /** Customers may only pay for orders they placed in this browser session. */
    private function authorizeOrder(Request $request, Order $order): void
    {
        $mine = array_map('intval', $request->session()->get('shop.orders', []));
        abort_unless(in_array($order->id, $mine, true), 404);
    }

    private function blockedReason(Order $order, string $method): ?string
    {
        if (! array_key_exists($method, StorePayments::available())) {
            return 'That payment option is not available right now. You can pay when you pick up your order.';
        }
        if ($order->paid_at) {
            return 'Order #'.$order->number.' is already paid.';
        }
        if ($order->status->value === 'canceled') {
            return 'Order #'.$order->number.' was canceled and can no longer be paid.';
        }

        return null;
    }

    public function show(Request $request, Order $order, string $method)
    {
        $this->authorizeOrder($request, $order);
        if ($reason = $this->blockedReason($order, $method)) {
            return redirect()->route('shop.orders')->with('success', $reason);
        }

        return view('shop.pay', [
            'order' => $order->load('items'),
            'method' => $method,
            'account' => StorePayments::available()[$method],
            'qr' => StorePayments::qr($method),
        ]);
    }

    public function store(Request $request, Order $order, string $method): RedirectResponse
    {
        $this->authorizeOrder($request, $order);
        if ($reason = $this->blockedReason($order, $method)) {
            return redirect()->route('shop.orders')->with('success', $reason);
        }

        $data = $request->validate([
            'reference' => ['required', 'string', 'min:6', 'max:40', 'regex:/^[A-Za-z0-9 \-]+$/'],
        ], [
            'reference.regex' => 'The reference number can only contain letters, numbers, spaces and dashes.',
            'reference.min' => 'Please enter the full reference number from your payment confirmation.',
        ]);

        $order->forceFill([
            'payment_choice' => $method,
            'customer_payment_ref' => trim($data['reference']),
            'payment_submitted_at' => now(),
        ])->save();

        return redirect()->route('shop.orders')->with('success', 'Thanks! We received your '.StorePayments::available()[$method]['label'].' reference for order #'.$order->number.'. The cashier will confirm your payment shortly.');
    }
}
