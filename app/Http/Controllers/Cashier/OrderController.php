<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $status = $request->enum('status', OrderStatus::class);
        if ($status === OrderStatus::Canceled) { abort_unless($request->user()->isManager(), 403); }

        $message = DB::transaction(function () use ($order, $status): ?string {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->paid_at && $status === OrderStatus::Canceled) {
                return 'A paid order cannot be canceled without a refund workflow.';
            }
            if ($status === OrderStatus::Completed && ! $locked->paid_at) {
                return 'Order #'.$locked->number.' must be paid before it can be completed.';
            }
            if (! $locked->status->canTransitionTo($status)) {
                return 'Order #'.$locked->number.' cannot move from '.$locked->status->label().' to '.$status->label().'.';
            }
            $locked->update(['status' => $status, 'canceled_at' => $status === OrderStatus::Canceled ? now() : $locked->canceled_at]);
            return null;
        });
        if ($message) {
            return back()->withErrors(['status' => $message]);
        }

        return back();
    }
}
