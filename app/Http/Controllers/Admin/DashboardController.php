<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $todayStart = now()->startOfDay();
        $weekStart = now()->subDays(6)->startOfDay();

        $todaySales = (float) Order::query()
            ->countedAsSales()
            ->placedBetween($todayStart, now()->endOfDay())
            ->sum('total');

        $weekOrders = Order::query()
            ->countedAsSales()
            ->placedBetween($weekStart, now()->endOfDay())
            ->get(['id', 'total', 'created_at'])
            ->groupBy(fn (Order $order): string => $order->created_at->toDateString());

        $salesByDay = collect(range(6, 0))->map(function (int $daysAgo) use ($weekOrders): array {
            $day = now()->subDays($daysAgo);
            $orders = $weekOrders->get($day->toDateString(), collect());

            return [
                'label' => $day->format('D'),
                'date' => $day->format('M j'),
                'orders' => $orders->count(),
                'total' => (float) $orders->sum('total'),
            ];
        });

        $topDishes = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.canteen_id', \App\Support\CurrentCanteen::id())
            ->where('orders.status', '!=', OrderStatus::Canceled->value)
            ->whereBetween('orders.created_at', [$weekStart, now()->endOfDay()])
            ->selectRaw('order_items.name as name, sum(order_items.quantity) as quantity')
            ->groupBy('order_items.name')
            ->orderByDesc('quantity')
            ->limit(5)
            ->get();

        return view('Admin.dashboard', [
            'todaySales' => $todaySales,
            'cashCollected' => Order::whereBetween('paid_at', [$todayStart, now()->endOfDay()])->whereNull('refunded_at')->sum('amount_paid'),
            'unpaidTotal' => Order::whereNull('paid_at')->countedAsSales()->sum('total'),
            'todayOrders' => Order::query()->countedAsSales()->where('created_at', '>=', $todayStart)->count(),
            'activeOrders' => Order::query()->whereIn('status', OrderStatus::active())->count(),
            'availableDishes' => MenuItem::query()->available()->count(),
            'totalDishes' => MenuItem::query()->count(),
            'cashierCount' => User::query()->where('role', UserRole::Cashier->value)->where('canteen_id', \App\Support\CurrentCanteen::id())->count(),
            'salesByDay' => $salesByDay,
            'maxDailySales' => max(1.0, (float) $salesByDay->max('total')),
            'topDishes' => $topDishes,
            'recentOrders' => Order::query()
                ->with(['user'])
                ->withSum('items as items_count', 'quantity')
                ->latest()
                ->limit(6)
                ->get(),
        ]);
    }
}
