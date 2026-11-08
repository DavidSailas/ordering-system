<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = OrderStatus::tryFrom((string) $request->query('status'));
        $type = OrderType::tryFrom((string) $request->query('type'));
        $search = trim((string) $request->query('q', ''));

        $orders = Order::query()
            ->with(['user'])
            ->withSum('items as items_count', 'quantity')
            ->when($status, fn (Builder $query) => $query->where('status', $status->value))
            ->when($type, fn (Builder $query) => $query->where('type', $type->value))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $reference = ltrim($search, '#');

                $query->where(function (Builder $query) use ($search, $reference): void {
                    $query->where('customer_name', 'like', '%'.$search.'%')
                        ->when(ctype_digit($reference), fn (Builder $query) => $query->orWhere('canteen_number', (int) $reference));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('Admin.orders.index', [
            'orders' => $orders,
            'status' => $status,
            'type' => $type,
            'search' => $search,
        ]);
    }

    public function show(Order $order): View
    {
        Gate::authorize('view', $order);

        return view('Admin.orders.show', [
            'order' => $order->load(['items', 'user']),
        ]);
    }
}
