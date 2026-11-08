<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportFilterRequest;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    private const MAX_RANGE_DAYS = 366;

    public function index(ReportFilterRequest $request): View
    {
        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->validated('from'))->startOfDay()
            : now()->toImmutable()->startOfMonth();

        $to = $request->filled('to')
            ? CarbonImmutable::parse($request->validated('to'))->endOfDay()
            : now()->toImmutable()->endOfDay();

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            $from = $to->subDays(self::MAX_RANGE_DAYS)->startOfDay();
        }

        $orders = Order::query()
            ->countedAsSales()
            ->placedBetween($from, $to)
            ->with('user:id,name')
            ->get(['id', 'user_id', 'type', 'total', 'created_at']);

        $revenue = (float) $orders->sum('total');

        $itemRows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->leftJoin('categories', 'categories.id', '=', 'menu_items.category_id')
            ->where('orders.canteen_id', \App\Support\CurrentCanteen::id())
            ->where('orders.status', '!=', OrderStatus::Canceled->value)
            ->whereBetween('orders.created_at', [$from, $to])
            ->selectRaw('order_items.name as dish, categories.name as category, sum(order_items.quantity) as quantity, sum(order_items.price * order_items.quantity) as revenue')
            ->groupBy('order_items.name', 'categories.name')
            ->get();

        $ordersByDay = $orders->groupBy(fn (Order $order): string => $order->created_at->toDateString());

        $days = collect();

        for ($day = $to->startOfDay(); $day >= $from->startOfDay(); $day = $day->subDay()) {
            $dayOrders = $ordersByDay->get($day->toDateString(), collect());

            $days->push([
                'date' => $day,
                'orders' => $dayOrders->count(),
                'revenue' => (float) $dayOrders->sum('total'),
            ]);
        }

        return view('Admin.report.index', [
            'from' => $from,
            'to' => $to,
            'revenue' => $revenue,
            'orderCount' => $orders->count(),
            'averageOrder' => $orders->isEmpty() ? 0.0 : $revenue / $orders->count(),
            'itemsSold' => (int) $itemRows->sum('quantity'),
            'canceledCount' => Order::query()
                ->where('status', OrderStatus::Canceled->value)
                ->placedBetween($from, $to)
                ->count(),
            'days' => $days,
            'maxDayRevenue' => max(1.0, (float) $days->max('revenue')),
            'byType' => $orders
                ->groupBy(fn (Order $order): string => $order->type->label())
                ->map(fn ($group): array => ['orders' => $group->count(), 'revenue' => (float) $group->sum('total')])
                ->sortByDesc('revenue'),
            'byCashier' => $orders
                ->groupBy(fn (Order $order): string => $order->user?->name ?? 'Unknown')
                ->map(fn ($group): array => ['orders' => $group->count(), 'revenue' => (float) $group->sum('total')])
                ->sortByDesc('revenue'),
            'byCategory' => $itemRows
                ->groupBy(fn (object $row): string => $row->category ?? 'Uncategorized')
                ->map(fn ($group): array => ['quantity' => (int) $group->sum('quantity'), 'revenue' => (float) $group->sum('revenue')])
                ->sortByDesc('revenue'),
            'topDishes' => $itemRows
                ->groupBy('dish')
                ->map(fn ($group): array => ['quantity' => (int) $group->sum('quantity'), 'revenue' => (float) $group->sum('revenue')])
                ->sortByDesc('quantity')
                ->take(10),
        ]);
    }
}
