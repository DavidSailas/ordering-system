<?php

namespace App\Http\Controllers\Runner;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\View\View;

/**
 * The runner's home page: paid delivery orders that are ready and where to bring them.
 * Picking up, delivering and collecting cash arrive with the order workflow phase.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $orders = Order::query()
            ->with('items')
            ->where('status', OrderStatus::ReadyToServe->value)
            // Only real delivery orders, and only once they are paid: a runner never carries an unpaid order.
            ->where('type', OrderType::Delivery->value)
            ->whereNotNull('paid_at')
            ->orderBy('building')
            ->orderBy('floor')
            ->orderBy('room')
            ->oldest()
            ->get();

        return view('Runner.dashboard', ['orders' => $orders]);
    }
}
