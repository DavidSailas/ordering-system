@use('App\Enums\OrderStatus')
@use('App\Enums\OrderType')

@php($currency = config('restaurant.currency'))

<x-layouts.admin title="Orders">
    <x-admin.page-title title="Orders" subtitle="Every order placed from the cashier screen" />

    <form method="GET" action="{{ route('admin.orders.index') }}" class="mb-5 flex flex-wrap items-center gap-2 rounded-2xl bg-white/60 p-3">
        <div class="flex min-w-48 flex-1 items-center gap-2 rounded-xl bg-white px-3 py-2">
            <x-icon name="search" class="size-4 text-neutral-400" />
            <input type="search" name="q" value="{{ $search }}" placeholder="Customer name or order #" class="w-full border-0 bg-transparent p-0 text-sm focus:outline-none">
        </div>

        <select name="status" aria-label="Status" class="rounded-xl border-0 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400">
            <option value="">All statuses</option>
            @foreach (OrderStatus::cases() as $case)
                <option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }}</option>
            @endforeach
        </select>

        <select name="type" aria-label="Order type" class="rounded-xl border-0 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400">
            <option value="">All types</option>
            @foreach (OrderType::cases() as $case)
                <option value="{{ $case->value }}" @selected($type === $case)>{{ $case->label() }}</option>
            @endforeach
        </select>

        <button type="submit" class="rounded-full bg-neutral-900 px-5 py-2 text-sm font-medium text-white hover:bg-neutral-700">Filter</button>

        @if ($status || $type || $search !== '')
            <a href="{{ route('admin.orders.index') }}" class="text-sm font-medium underline underline-offset-2">Reset</a>
        @endif
    </form>

    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm">
        <table class="w-full min-w-[44rem] text-left text-sm">
            <thead class="text-xs text-neutral-500">
                <tr class="border-b border-neutral-100">
                    <th class="px-4 py-3 font-medium">Order</th>
                    <th class="px-4 py-3 font-medium">Customer</th>
                    <th class="px-4 py-3 font-medium">Deliver to / type</th>
                    <th class="px-4 py-3 font-medium">Items</th>
                    <th class="px-4 py-3 text-right font-medium">Total</th>
                    <th class="px-4 py-3 font-medium">Cashier</th>
                    <th class="px-4 py-3 font-medium">Placed</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse ($orders as $order)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3"><a href="{{ route('admin.orders.show', $order) }}" class="font-semibold underline underline-offset-2">#{{ $order->number }}</a></td>
                        <td class="px-4 py-3">{{ $order->customer_name }}</td>
                        <td class="px-4 py-3 text-neutral-600">{{ $order->location ?? $order->type->label() }}</td>
                        <td class="px-4 py-3">{{ (int) $order->items_count }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ $currency }}{{ number_format($order->total, 2) }}</td>
                        <td class="px-4 py-3 text-neutral-600">{{ $order->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-neutral-600">{{ $order->created_at->format('M j, g:i A') }}</td>
                        <td class="px-4 py-3"><x-admin.status-badge :status="$order->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-neutral-500">No orders match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-5">
        {{ $orders->links() }}
    </div>
</x-layouts.admin>
