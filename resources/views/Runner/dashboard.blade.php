<x-layouts.runner title="Deliveries">
    <x-admin.page-title title="Deliveries" subtitle="Ready orders, sorted by building and room" />

    @forelse ($orders as $order)
        <div class="mb-3 rounded-2xl bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-lg font-semibold">{{ $order->building }}@if ($order->room) · Room {{ $order->room }}@endif</p>
                <span class="rounded-full bg-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-950">Ready</span>
            </div>
            <p class="mt-1 text-sm text-neutral-600">
                #{{ str_pad((string) ($order->canteen_number ?? $order->id), 4, '0', STR_PAD_LEFT) }} · {{ $order->customer_name }}@if ($order->phone) · {{ $order->phone }}@endif
            </p>
            <ul class="mt-3 text-sm text-neutral-700">
                @foreach ($order->items as $item)
                    <li>{{ $item->quantity }} × {{ $item->name }}</li>
                @endforeach
            </ul>
        </div>
    @empty
        <div class="rounded-2xl bg-white p-8 text-center text-sm text-neutral-500 shadow-sm">
            No orders are waiting for delivery right now.
        </div>
    @endforelse
</x-layouts.runner>
