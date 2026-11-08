<?php

namespace App\Http\Controllers\Runner;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\View\View;

/**
 * The runner's home page: orders that are ready and where to bring them.
 * Picking up, delivering and collecting cash arrive with the order workflow phase.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $orders = Order::query()
            ->with('items')
            ->where('status', OrderStatus::ReadyToServe->value)
            ->whereNotNull('building')
            ->orderBy('building')
            ->orderBy('room')
            ->oldest()
            ->get();

        return view('Runner.dashboard', ['orders' => $orders]);
    }
}
