<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Canteen;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Overall administrator only: see every canteen, add a canteen, switch which one you are managing. */
class CanteenController extends Controller
{
    public function index(Request $request): View
    {
        $canteens = Canteen::query()->orderBy('sort_order')->orderBy('id')->get()->map(function (Canteen $canteen) {
            $orders = fn () => Order::withoutGlobalScopes()->where('canteen_id', $canteen->id);

            $canteen->stats = [
                'dishes' => MenuItem::withoutGlobalScopes()->where('canteen_id', $canteen->id)->count(),
                'staff' => User::query()->where('canteen_id', $canteen->id)->count(),
                'orders_today' => $orders()->whereDate('created_at', today())->count(),
                'active' => $orders()->where('status', OrderStatus::InProgress->value)->count(),
                'sales_today' => (float) $orders()->whereDate('paid_at', today())->whereNull('refunded_at')->sum('amount_paid'),
            ];

            return $canteen;
        });

        return view('Admin.canteens.index', [
            'canteens' => $canteens,
            'managingId' => (int) $request->session()->get('staff.canteen_id', $canteens->first()?->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $slug = Str::slug($data['name']) ?: 'canteen';
        $base = $slug;
        $i = 2;

        while (Canteen::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        $canteen = Canteen::create([
            'name' => $data['name'],
            'slug' => $slug,
            'accent' => strtolower($data['accent'] ?? '#e25806'),
            'sort_order' => (int) Canteen::query()->max('sort_order') + 1,
        ]);

        $request->session()->put('staff.canteen_id', $canteen->id);

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', $canteen->name.' was created. Finish its details below, then add its owner under Staff.');
    }

    public function switch(Request $request, Canteen $canteen): RedirectResponse
    {
        $request->session()->put('staff.canteen_id', $canteen->id);

        return redirect()
            ->route('admin.dashboard')
            ->with('status', 'Now managing '.$canteen->name.'.');
    }
}
