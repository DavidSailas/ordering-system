<?php
namespace App\Http\Controllers;
use App\Models\Canteen;
use App\Models\MenuItem;
use App\Models\Order;
use App\Support\ShopCart;
use App\Support\StorePayments;
use App\Enums\OrderType;
use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        return view('shop.checkout', compact('lines'));
    }
    public function purchase(Request $request)
    {
        $data = $request->validate(['customer_name' => ['required', 'string', 'max:120'], 'phone' => ['required', 'string', 'regex:/^[0-9+() \-]{7,25}$/'], 'building' => ['nullable', 'string', 'max:80', 'required_without:room'], 'room' => ['nullable', 'string', 'max:40', 'required_without:building'], 'notes' => ['nullable', 'string', 'max:1000'], 'payment_choice' => ['required', 'in:cash,gcash,maya'], 'checkout_token' => ['required', 'string']]);
        if (! config('restaurant.accepting_orders', true)) { throw ValidationException::withMessages(['cart' => 'Sorry, we are not accepting orders right now. Please try again later.']); }
        if (config('restaurant.buildings') && filled($data['building'] ?? null) && ! in_array($data['building'], config('restaurant.buildings'), true)) { throw ValidationException::withMessages(['building' => 'Please choose a building from the list.']); }
        if ($data['payment_choice'] !== 'cash' && ! array_key_exists($data['payment_choice'], StorePayments::available())) { throw ValidationException::withMessages(['payment_choice' => 'That payment option is not available right now.']); }
        $order = DB::transaction(function () use ($request, $data) {
            if (!hash_equals((string) $request->session()->get('shop.checkout_token', ''), $data['checkout_token'])) { throw ValidationException::withMessages(['cart' => 'This checkout has already been submitted.']); }
            $cart = ShopCart::for($request)->quantities();
            if (!$cart) { throw ValidationException::withMessages(['cart' => 'Your cart is empty.']); }
            $foods = MenuItem::whereIn('id', array_keys($cart))->lockForUpdate()->get()->keyBy('id');
            if ($foods->count() !== count($cart) || $foods->contains(fn ($food) => !$food->is_available)) { throw ValidationException::withMessages(['cart' => 'An item is no longer available. Please review your cart.']); }
            $total = 0;
            foreach ($cart as $id => $quantity) { $total += (int) round((float) $foods[$id]->price * 100) * $quantity; }
            $order = Order::create(['customer_name' => $data['customer_name'], 'phone' => $data['phone'], 'building' => $data['building'] ?? null, 'room' => $data['room'] ?? null, 'type' => OrderType::TakeAway, 'status' => OrderStatus::InProgress, 'payment_choice' => $data['payment_choice'], 'notes' => $data['notes'] ?? null, 'total' => $total / 100]);
            foreach ($cart as $id => $quantity) { $food = $foods[$id]; $order->items()->create(['menu_item_id' => $id, 'name' => $food->name, 'price' => $food->price, 'quantity' => $quantity]); }
            ShopCart::for($request)->clear();
            return $order;
        });
        $request->session()->put('shop.profile', ['customer_name' => $data['customer_name'], 'phone' => $data['phone'], 'building' => $data['building'] ?? null, 'room' => $data['room'] ?? null]);
        $request->session()->push('shop.orders', $order->id);
        $request->session()->forget('shop.checkout_token');
        if ($data['payment_choice'] !== 'cash') { return redirect()->route('shop.pay', [$order, $data['payment_choice']])->with('success', 'Order #'.$order->number.' placed! Send your payment below to complete it.'); }
        return redirect()->route('shop.orders')->with('success', 'Order #'.$order->number.' placed! Pay in cash when you receive it.');
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
        return view('shop.orders', ['orders' => Order::with(['items', 'canteen'])->whereIn('id', $request->session()->get('shop.orders', []))->latest()->get()]);
    }
}
