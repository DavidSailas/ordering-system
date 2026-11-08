@use('App\Enums\OrderStatus')

@php
    $currency = config('restaurant.currency');
    $next = $order->status->next();
@endphp

<x-layouts.admin :title="'Order #'.$order->number">
    <x-admin.page-title :title="'Order #'.$order->number" :subtitle="'Placed '.$order->created_at->format('M j, Y · g:i A')">
        <a href="{{ route('admin.orders.index') }}" class="flex items-center gap-1 rounded-full bg-white/60 px-4 py-2 text-sm font-medium hover:bg-white">
            <x-icon name="chevron-left" class="size-4" />
            All orders
        </a>
    </x-admin.page-title>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section class="rounded-2xl bg-white p-5 shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="text-xs text-neutral-500">
                    <tr class="border-b border-neutral-100">
                        <th class="py-2 font-medium">Dish</th>
                        <th class="py-2 text-right font-medium">Price</th>
                        <th class="py-2 text-right font-medium">Qty</th>
                        <th class="py-2 text-right font-medium">Line total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach ($order->items as $line)
                        <tr>
                            <td class="py-3 font-medium">{{ $line->name }}</td>
                            <td class="py-3 text-right text-neutral-600">{{ $currency }}{{ number_format($line->price, 2) }}</td>
                            <td class="py-3 text-right">{{ $line->quantity }}</td>
                            <td class="py-3 text-right font-semibold">{{ $currency }}{{ number_format($line->price * $line->quantity, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-neutral-200">
                        <td colspan="3" class="pt-3 text-right text-neutral-600">Order total</td>
                        <td class="pt-3 text-right text-base font-semibold">{{ $currency }}{{ number_format($order->total, 2) }}</td>
                    </tr>
                </tfoot>
            </table>

            @if ($order->notes)
                <div class="mt-4 rounded-xl bg-violet-100 px-4 py-3 text-sm">
                    <span class="font-medium">Cooking request:</span> {{ $order->notes }}
                </div>
            @endif
        </section>

        <aside class="space-y-3 self-start rounded-3xl bg-violet-200 p-4 text-sm">
            <h2 class="text-lg font-semibold">Details</h2>
            <a href="{{ route('orders.payment', $order) }}" class="block rounded-full bg-white px-4 py-3 text-center font-medium">{{ $order->paid_at ? 'Paid · View receipt' : 'Unpaid · Collect cash' }}</a>

            <dl class="space-y-2 rounded-2xl bg-white/70 p-4">
                <div class="flex justify-between gap-3"><dt class="text-neutral-500">Status</dt><dd><x-admin.status-badge :status="$order->status" /></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-neutral-500">Customer</dt><dd class="font-medium">{{ $order->customer_name }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-neutral-500">Type</dt><dd class="font-medium">{{ $order->type->label() }}</dd></div>
                @if ($order->location)
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Deliver to</dt><dd class="font-medium text-right">{{ $order->location }}</dd></div>
                @endif
                @if ($order->phone)
                    <div class="flex justify-between gap-3"><dt class="text-neutral-500">Phone</dt><dd class="font-medium">{{ $order->phone }}</dd></div>
                @endif
                <div class="flex justify-between gap-3"><dt class="text-neutral-500">Cashier</dt><dd class="font-medium">{{ $order->user?->name ?? '—' }}</dd></div>
            </dl>

            @unless ($order->status->isFinal())
                <div class="flex flex-col gap-2">
                    @if ($next)
                        <form method="POST" action="{{ route('orders.status', $order) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $next->value }}">
                            <button type="submit" class="w-full rounded-full bg-neutral-900 py-3 font-semibold text-white hover:bg-neutral-700">Mark as {{ $next->label() }}</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('orders.status', $order) }}" data-confirm="Cancel order #{{ $order->number }}?" data-confirm-title="Cancel order?" data-confirm-ok="Yes, cancel order">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ OrderStatus::Canceled->value }}">
                        <button type="submit" class="w-full rounded-full bg-white/70 py-3 font-medium text-rose-700 hover:bg-rose-100">Cancel order</button>
                    </form>
                </div>
            @endunless
        </aside>
    </div>
</x-layouts.admin>
