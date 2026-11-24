<?php
namespace App\Http\Controllers;
use App\Models\BlockedPhone;
use App\Models\Canteen;
use App\Models\DeliveryZone;
use App\Models\MenuItem;
use App\Models\Order;
use App\Support\PhoneKey;
use App\Support\ShopCart;
use App\Support\StorePayments;
use App\Enums\OrderType;
use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class StorefrontController extends Controller
{
    private function cart(Request $request): array
    {
        $quantities = ShopCart::for($request)->quantities();
        return MenuItem::with('category')->whereIn('id', array_keys($quantities))->get()
            ->map(fn ($food) => ['food' => $food, 'quantity' => $quantities[$food->id], 'cents' => (int) round((float) $food->price * 100) * $quantities[$food->id]])->all();
    }
    /** "Choose a canteen" page. */
    public function canteens(Request $request)
    {
        $canteens = Canteen::query()->orderBy('sort_order')->orderBy('id')->get();

        if ($canteens->count() === 1) {
            $request->session()->put('shop.canteen_id', $canteens->first()->id);

            return redirect()->route('shop.menu');
        }

        // a small preview of each canteen's menu for the picker cards
        $previews = $canteens->mapWithKeys(function (Canteen $canteen) {
            $dishes = MenuItem::withoutGlobalScopes()->where('canteen_id', $canteen->id)->where('is_available', true)->get();

            return [$canteen->id => [
                'count' => $dishes->count(),
                'from' => $dishes->min('price'),
                'images' => $dishes->whereNotNull('image_path')->shuffle()->take(3)->map(fn (MenuItem $dish) => $dish->image_url)->values()->all(),
            ]];
        });

        return view('shop.canteens', [
            'previews' => $previews,
            'canteens' => $canteens,
            'currentId' => (int) $request->session()->get('shop.canteen_id', 0),
            'cartCount' => ShopCart::for($request)->count(),
        ]);
    }

    public function chooseCanteen(Request $request, Canteen $canteen)
    {
        // a cart can only hold one canteen's dishes
        ShopCart::for($request)->removeOtherCanteens($canteen->id);

        if ((int) $request->session()->get('shop.canteen_id', 0) !== $canteen->id) {
            $request->session()->forget('shop.checkout_token');
        }

        $request->session()->put('shop.canteen_id', $canteen->id);

        return redirect()->route('shop.menu');
    }

    public function index(Request $request)
    {
        $foods = MenuItem::with('category')->orderBy('name')->get();
        return view('shop.menu', ['foods' => $foods, 'lines' => $this->cart($request), 'recentOrders' => Order::whereIn('id', $request->session()->get('shop.orders', []))->latest()->limit(4)->get()]);
    }
    public function basket(Request $request)
    {
        return view('shop.cart', ['lines' => $this->cart($request)]);
    }
    public function update(Request $request)
    {
        $data = $request->validate(['menu_item_id' => ['required', 'integer', 'exists:menu_items,id'], 'quantity' => ['required', 'integer', 'min:0', 'max:99'], 'action' => ['required', 'in:add,set']]);
        $food = MenuItem::findOrFail($data['menu_item_id']);
        if ($data['quantity'] > 0 && !$food->is_available) { throw ValidationException::withMessages(['cart' => 'This item is currently unavailable.']); }
        $shopCart = ShopCart::for($request);
        $cart = $shopCart->quantities();
        $quantity = $data['action'] === 'add' ? ($cart[$food->id] ?? 0) + $data['quantity'] : $data['quantity'];
        if ($quantity > 99) { throw ValidationException::withMessages(['cart' => 'Maximum quantity is 99 per item.']); }
        $shopCart->set($food->id, $quantity);
        if ($quantity === 0) { unset($cart[$food->id]); } else { $cart[$food->id] = $quantity; }
        if ($request->expectsJson()) { return response()->json(['message' => $food->name.' added to cart', 'count' => array_sum($cart)]); }
        return back()->with('success', $quantity === 0 ? 'Item removed.' : 'Cart updated.');
    }
    public function checkout(Request $request)
    {
        $lines = $this->cart($request);
        if (!$lines) { return redirect()->route('shop.cart')->withErrors(['cart' => 'Add an item before checking out.']); }
        $request->session()->put('shop.checkout_token', $request->session()->get('shop.checkout_token', bin2hex(random_bytes(24))));
        $request->session()->put('shop.checkout_shown_at', now()->timestamp);

        // Delivery buildings of the canteen the customer picked. The fee table is worked out here, on the
        // server, so the page only ever shows prices the server itself would charge.
        $deliveryZones = config('restaurant.delivery_enabled', true)
            ? DeliveryZone::query()->active()->orderBy('sort_order')->orderBy('id')->get()
            : collect();
        $pickupAt = DeliveryZone::query()->where('is_home', true)->first();

        return view('shop.checkout', compact('lines', 'deliveryZones', 'pickupAt'));
    }
    public function purchase(Request $request)
    {
        $delivering = $request->input('fulfillment') === 'delivery';
        // Only these are read from the browser. The delivery fee and the total are NEVER taken from it.
        $data = $request->validate([
            // The name is optional when a school ID is tapped: it then comes from the school list, not from the form.
            'customer_name' => ['required_without:school_id', 'nullable', 'string', 'max:120'],
            'school_id' => ['nullable', 'string', 'max:40'],
            'school_last_name' => ['nullable', 'string', 'max:80'],
            'phone' => ['required', 'string', 'regex:/^[0-9+() \-]{7,25}$/'],
            'fulfillment' => ['nullable', 'in:pickup,delivery'],
            'zone_id' => [Rule::requiredIf($delivering), 'nullable', 'integer'],
            'floor' => [Rule::requiredIf($delivering), 'nullable', 'integer', 'min:1', 'max:100'],
            'room' => [Rule::requiredIf($delivering), 'nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_choice' => ['required', 'in:cash,gcash,maya'],
            'checkout_token' => ['required', 'string'],
        ], ['zone_id.required' => 'Please choose a building.', 'floor.required' => 'Please choose your floor.', 'room.required' => 'Please enter your room number so the runner can find you.']);
        $this->rejectBots($request);
        if (BlockedPhone::isBlocked($data['phone'])) { throw ValidationException::withMessages(['phone' => 'We cannot take orders from this phone number. Please talk to the canteen staff.']); }
        $member = $this->resolveSchoolMember($request, $data);
        if ($pause = \App\Support\Strikes::blockingPause($data['phone'], $member)) { throw ValidationException::withMessages([$pause->school_member_id ? 'school_id' : 'phone' => 'Ordering is paused for this '.($pause->school_member_id ? 'school ID' : 'phone number').' until '.$pause->ends_at->format('M j, g:i A').' because earlier orders were not paid. You can order again after that.']); }
        $this->guardAgainstAbuse($request, PhoneKey::from($data['phone']), $member?->id);
        if (! config('restaurant.accepting_orders', true)) { throw ValidationException::withMessages(['cart' => 'Sorry, we are not accepting orders right now. Please try again later.']); }
        [$zone, $deliveryCents] = $this->resolveDelivery($data);
        if ($data['payment_choice'] !== 'cash' && ! array_key_exists($data['payment_choice'], StorePayments::available())) { throw ValidationException::withMessages(['payment_choice' => 'That payment option is not available right now.']); }
        $order = DB::transaction(function () use ($request, $data, $zone, $deliveryCents, $member) {
            if (!hash_equals((string) $request->session()->get('shop.checkout_token', ''), $data['checkout_token'])) { throw ValidationException::withMessages(['cart' => 'This checkout has already been submitted.']); }
            $cart = ShopCart::for($request)->quantities();
            if (!$cart) { throw ValidationException::withMessages(['cart' => 'Your cart is empty.']); }
            $foods = MenuItem::whereIn('id', array_keys($cart))->lockForUpdate()->get()->keyBy('id');
            if ($foods->count() !== count($cart) || $foods->contains(fn ($food) => !$food->is_available)) { throw ValidationException::withMessages(['cart' => 'An item is no longer available. Please review your cart.']); }
            $total = 0;
            foreach ($cart as $id => $quantity) { $total += (int) round((float) $foods[$id]->price * 100) * $quantity; }
            $cashCap = (float) config('restaurant.order_limits.max_cash_total', 0);
            if ($data['payment_choice'] === 'cash' && $cashCap > 0 && $total / 100 > $cashCap) { throw ValidationException::withMessages(['payment_choice' => 'Cash orders can be up to '.config('restaurant.currency').number_format($cashCap, 0).'. For a bigger order please pay with GCash or Maya.']); }
            $total += $deliveryCents;   // the fee is saved on the order, so later price changes never rewrite it
            $order = Order::create(['user_id' => $request->user()?->id, 'school_member_id' => $member?->id, 'customer_name' => $member?->name ?? $data['customer_name'], 'phone' => $data['phone'], 'phone_key' => PhoneKey::from($data['phone']), 'ip_address' => $request->ip(), 'building' => $zone?->name, 'floor' => $zone ? (int) $data['floor'] : null, 'room' => $zone ? trim($data['room']) : null, 'type' => $zone ? OrderType::Delivery : OrderType::TakeAway, 'status' => OrderStatus::AwaitingPayment, 'payment_choice' => $data['payment_choice'], 'notes' => $data['notes'] ?? null, 'delivery_fee' => $deliveryCents / 100, 'total' => $total / 100]);
            foreach ($cart as $id => $quantity) { $food = $foods[$id]; $order->items()->create(['menu_item_id' => $id, 'name' => $food->name, 'price' => $food->price, 'quantity' => $quantity]); }
            ShopCart::for($request)->clear();
            return $order;
        });
        $request->session()->put('shop.profile', ['customer_name' => $member?->name ?? $data['customer_name'], 'phone' => $data['phone'], 'zone_id' => $zone?->id, 'floor' => $zone ? (int) $data['floor'] : null, 'room' => $data['room'] ?? null]);
        $request->session()->push('shop.orders', $order->id);
        $request->session()->forget('shop.checkout_token');
        $minutes = max(1, (int) config('restaurant.unpaid_expiry_minutes', 15));
        if ($data['payment_choice'] !== 'cash') { return redirect()->route('shop.pay', [$order, $data['payment_choice']])->with('success', 'Order #'.$order->number.' placed! Send your payment below within '.$minutes.' minutes and we will start preparing it'.($zone ? ', then bring it to you.' : '.')); }
        return redirect()->route('shop.orders')->with('success', 'Order #'.$order->number.' placed! Please pay at the counter within '.$minutes.' minutes so we can start preparing it.');
    }
    /**
     * ID tap. With an ID typed, scanned or tapped, the person must be on the school list (ID number + last name) and
     * not blocked. When the administrator makes the ID compulsory, an order without one is refused.
     */
    private function resolveSchoolMember(Request $request, array $data): ?\App\Models\SchoolMember
    {
        if (blank($data['school_id'] ?? null)) {
            if (\App\Support\SchoolId::required()) { throw ValidationException::withMessages(['school_id' => 'Please tap or scan your school ID to place an order.']); }
            return null;
        }
        $result = \App\Support\SchoolRoster::verify($data['school_id'], (string) ($data['school_last_name'] ?? ''), $request->ip());
        if (is_string($result)) { throw ValidationException::withMessages(['school_id' => $result]); }
        return $result;
    }
    /**
     * Delivery rules, all decided here on the server:
     *  - delivery must be switched on, and the building must be one of this canteen's active buildings;
     *  - the floor must really exist in that building;
     *  - delivery is paid in advance (GCash / Maya), so a prank order never costs the canteen or a runner anything;
     *  - the fee is calculated from the canteen's own prices; nothing the browser sends can change it.
     *
     * @return array{0: DeliveryZone|null, 1: int} the building (null = pickup) and the fee in centavos
     */
    private function resolveDelivery(array $data): array
    {
        if (($data['fulfillment'] ?? 'pickup') !== 'delivery') { return [null, 0]; }
        if (! config('restaurant.delivery_enabled', true)) { throw ValidationException::withMessages(['fulfillment' => 'Delivery is not available right now. Please choose pickup instead.']); }
        $floor = (int) ($data['floor'] ?? 0);
        $zone = DeliveryZone::query()->active()->find((int) ($data['zone_id'] ?? 0));
        if (! $zone || ! $zone->acceptsFloor($floor)) { throw ValidationException::withMessages(['floor' => 'Please choose a building and a floor from the list.']); }
        if (! in_array($data['payment_choice'], ['gcash', 'maya'], true)) { throw ValidationException::withMessages(['payment_choice' => 'Delivery orders must be paid in advance with GCash or Maya.']); }
        return [$zone, $zone->feeCentsForFloor($floor)];
    }
    /** Scripts fill forms in at once and fill in fields people cannot see. People do neither. */
    private function rejectBots(Request $request): void
    {
        $shownAt = (int) $request->session()->get('shop.checkout_shown_at', 0);

        if (filled($request->input('website')) || ($shownAt > 0 && now()->timestamp - $shownAt < 3)) {
            throw ValidationException::withMessages(['cart' => 'Please take a moment to review your order, then press Place order again.']);
        }
    }

    /** One phone number (or one connection) cannot pile up unpaid orders: the usual way to prank a canteen. */
    private function guardAgainstAbuse(Request $request, string $phoneKey, ?int $memberId = null): void
    {
        $open = fn () => Order::withoutGlobalScopes()
            ->whereNull('paid_at')
            ->whereNotIn('status', [OrderStatus::Completed->value, OrderStatus::Canceled->value]);

        $perPhone = (int) config('restaurant.order_limits.max_open_unpaid', 0);
        if ($perPhone > 0 && $phoneKey !== '' && $open()->where('phone_key', $phoneKey)->count() >= $perPhone) {
            throw ValidationException::withMessages(['phone' => 'This phone number already has '.$perPhone.' unpaid '.Str::plural('order', $perPhone).' in progress. Please wait until they are paid or finished before ordering again.']);
        }

        if ($perPhone > 0 && $memberId && $open()->where('school_member_id', $memberId)->count() >= $perPhone) {
            throw ValidationException::withMessages(['school_id' => 'This school ID already has '.$perPhone.' unpaid '.Str::plural('order', $perPhone).' in progress. Please wait until they are paid or finished before ordering again.']);
        }

        $perIp = (int) config('restaurant.order_limits.max_open_unpaid_per_ip', 0);
        if ($perIp > 0 && $request->ip() && $open()->where('ip_address', $request->ip())->count() >= $perIp) {
            throw ValidationException::withMessages(['cart' => 'There are too many unpaid orders from this connection right now. Please try again in a little while.']);
        }
    }

    public function profile(Request $request)
    {
        return view('shop.profile', [
            'profile' => $request->session()->get('shop.profile', []),
            'orderCount' => count($request->session()->get('shop.orders', [])),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^[0-9+() \-]{7,25}$/'],
            'building' => ['nullable', 'string', 'max:80'],
            'room' => ['nullable', 'string', 'max:40'],
        ]);
        $request->session()->put('shop.profile', $data);
        return redirect()->route('shop.profile')->with('success', 'Your details have been saved for this browser session.');
    }

    public function orders(Request $request)
    {
        \App\Support\UnpaidOrders::expireThrottled();
        return view('shop.orders', ['orders' => Order::with(['items', 'canteen'])->whereIn('id', $request->session()->get('shop.orders', []))->latest()->get()]);
    }
}
