<?php
namespace App\Http\Controllers;
use App\Models\MenuItem;
use App\Models\Order;
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
        $quantities = $request->session()->get('shop.cart', []);
        return MenuItem::with('category')->whereIn('id', array_keys($quantities))->get()
            ->map(fn ($food) => ['food' => $food, 'quantity' => $quantities[$food->id], 'cents' => (int) round((float) $food->price * 100) * $quantities[$food->id]])->all();
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
        $cart = $request->session()->get('shop.cart', []);
        $quantity = $data['action'] === 'add' ? ($cart[$food->id] ?? 0) + $data['quantity'] : $data['quantity'];
        if ($quantity > 99) { throw ValidationException::withMessages(['cart' => 'Maximum quantity is 99 per item.']); }
        if ($quantity === 0) { unset($cart[$food->id]); } else { $cart[$food->id] = $quantity; }
        $request->session()->put('shop.cart', $cart);
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
        $data = $request->validate(['customer_name' => ['required', 'string', 'max:120'], 'phone' => ['required', 'string', 'regex:/^[0-9+() \-]{7,25}$/'], 'notes' => ['nullable', 'string', 'max:1000'], 'payment_choice' => ['required', 'in:cash,gcash,maya'], 'checkout_token' => ['required', 'string']]);
        if ($data['payment_choice'] !== 'cash' && ! array_key_exists($data['payment_choice'], StorePayments::available())) { throw ValidationException::withMessages(['payment_choice' => 'That payment option is not available right now.']); }
        $order = DB::transaction(function () use ($request, $data) {
            if (!hash_equals((string) $request->session()->get('shop.checkout_token', ''), $data['checkout_token'])) { throw ValidationException::withMessages(['cart' => 'This checkout has already been submitted.']); }
            $cart = $request->session()->get('shop.cart', []);
            if (!$cart) { throw ValidationException::withMessages(['cart' => 'Your cart is empty.']); }
            $foods = MenuItem::whereIn('id', array_keys($cart))->lockForUpdate()->get()->keyBy('id');
            if ($foods->count() !== count($cart) || $foods->contains(fn ($food) => !$food->is_available)) { throw ValidationException::withMessages(['cart' => 'An item is no longer available. Please review your cart.']); }
            $total = 0;
            foreach ($cart as $id => $quantity) { $total += (int) round((float) $foods[$id]->price * 100) * $quantity; }
            $order = Order::create(['customer_name' => $data['customer_name'], 'type' => OrderType::TakeAway, 'status' => OrderStatus::InProgress, 'payment_choice' => $data['payment_choice'], 'notes' => 'Website pickup. '.($data['payment_choice'] === 'cash' ? 'Pay at pickup.' : 'Paying via '.strtoupper($data['payment_choice']).'.').' Phone: '.$data['phone']."\n".($data['notes'] ?? ''), 'total' => $total / 100]);
            foreach ($cart as $id => $quantity) { $food = $foods[$id]; $order->items()->create(['menu_item_id' => $id, 'name' => $food->name, 'price' => $food->price, 'quantity' => $quantity]); }
            return $order;
        });
        $request->session()->put('shop.profile', ['customer_name' => $data['customer_name'], 'phone' => $data['phone']]);
        $request->session()->push('shop.orders', $order->id);
        $request->session()->forget(['shop.cart', 'shop.checkout_token']);
        if ($data['payment_choice'] !== 'cash') { return redirect()->route('shop.pay', [$order, $data['payment_choice']])->with('success', 'Order #'.$order->number.' placed! Send your payment below to complete it.'); }
        return redirect()->route('shop.orders')->with('success', 'Order #'.$order->number.' placed! Payment is due at pickup.');
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
        ]);
        $request->session()->put('shop.profile', $data);
        return redirect()->route('shop.profile')->with('success', 'Your details have been saved for this browser session.');
    }

    public function orders(Request $request)
    {
        return view('shop.orders', ['orders' => Order::with('items')->whereIn('id', $request->session()->get('shop.orders', []))->latest()->get()]);
    }
}
