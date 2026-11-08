<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The cashier's home page: a live board of incoming orders
 * (Preparing → Ready → Done today).
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $base = fn (): Builder => Order::query()
            ->with('items')
            ->withSum('items as items_count', 'quantity')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $number = (int) preg_replace('/\D/', '', $search);

                $query->where(function (Builder $query) use ($search, $number): void {
                    $query->where('customer_name', 'like', '%'.$search.'%')
                        ->orWhere('building', 'like', '%'.$search.'%')
                        ->orWhere('room', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');

                    if ($number > 0) {
                        $query->orWhere('canteen_number', $number);
                    }
                });
            });

        $preparing = $base()->where('status', OrderStatus::InProgress->value)->oldest()->get();
        $ready = $base()->where('status', OrderStatus::ReadyToServe->value)->oldest()->get();
        $finished = $base()
            ->whereIn('status', [OrderStatus::Completed->value, OrderStatus::Canceled->value])
            ->where('updated_at', '>=', now()->startOfDay())
            ->latest('updated_at')
            ->limit(20)
            ->get();

        $active = $preparing->concat($ready);

        return view('Cashier.dashboard', [
            'search' => $search,
            'preparing' => $preparing,
            'ready' => $ready,
            'finished' => $finished,
            'unpaidCount' => $active->reject(fn (Order $order) => $order->isPaid())->count(),
            'newestId' => (int) $active->max('id'),
        ]);
    }
}
