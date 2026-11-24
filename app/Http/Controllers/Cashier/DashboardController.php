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
 * (Waiting for payment → Preparing → Ready → Done today).
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        \App\Support\UnpaidOrders::expireThrottled();

        $search = trim((string) $request->query('q', ''));

        $base = fn (): Builder => Order::query()
            ->with(['items', 'schoolMember:id,id_number,kind,name'])
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

        $awaiting = $base()->where('status', OrderStatus::AwaitingPayment->value)->oldest()->get();
        $preparing = $base()->where('status', OrderStatus::InProgress->value)->oldest()->get();
        $ready = $base()->where('status', OrderStatus::ReadyToServe->value)->oldest()->get();
        $finished = $base()
            ->whereIn('status', [OrderStatus::Completed->value, OrderStatus::Canceled->value])
            ->where('updated_at', '>=', now()->startOfDay())
            ->latest('updated_at')
            ->limit(20)
            ->get();

        $active = $awaiting->concat($preparing)->concat($ready);

        return view('Cashier.dashboard', [
            'search' => $search,
            'awaiting' => $awaiting,
            'memberStrikes' => $awaiting->pluck('school_member_id')->filter()->unique()->mapWithKeys(fn ($id) => [$id => \App\Support\Strikes::currentForMember((int) $id)])->filter(),
            // missed orders per phone number, so the cashier can see who has missed some before
            'strikes' => $awaiting->pluck('phone_key')->filter()->unique()->mapWithKeys(fn ($key) => [$key => \App\Support\Strikes::current((string) $key)])->filter(),
            'preparing' => $preparing,
            'ready' => $ready,
            'finished' => $finished,
            'unpaidCount' => $active->reject(fn (Order $order) => $order->isPaid())->count(),
            'newestId' => (int) $active->max('id'),
        ]);
    }
}
