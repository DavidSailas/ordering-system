@php
    $currency = config('restaurant.currency');
    $money = fn (float $value): string => $currency.number_format($value, 2);

    $ranges = [
        'Today' => [now()->toDateString(), now()->toDateString()],
        'Last 7 days' => [now()->subDays(6)->toDateString(), now()->toDateString()],
        'Last 30 days' => [now()->subDays(29)->toDateString(), now()->toDateString()],
        'This month' => [now()->startOfMonth()->toDateString(), now()->toDateString()],
    ];
@endphp

<x-layouts.admin title="Reports">
    <x-admin.page-title title="Sales report" :subtitle="$from->format('M j, Y').' – '.$to->format('M j, Y').' · canceled orders are not counted'" />

    <div class="mb-5 flex flex-wrap items-center gap-2 rounded-2xl bg-white/60 p-3">
        <form method="GET" action="{{ route('admin.reports.index') }}" class="flex flex-wrap items-center gap-2">
            <label class="flex items-center gap-2 text-sm">
                From
                <input type="date" name="from" value="{{ $from->toDateString() }}" class="rounded-xl border-0 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400">
            </label>
            <label class="flex items-center gap-2 text-sm">
                To
                <input type="date" name="to" value="{{ $to->toDateString() }}" class="rounded-xl border-0 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400">
            </label>
            <button type="submit" class="rounded-full bg-neutral-900 px-5 py-2 text-sm font-medium text-white hover:bg-neutral-700">Apply</button>
        </form>

        <div class="ml-auto flex flex-wrap items-center gap-1 text-sm">
            @foreach ($ranges as $label => [$rangeFrom, $rangeTo])
                <a href="{{ route('admin.reports.index', ['from' => $rangeFrom, 'to' => $rangeTo]) }}" class="rounded-full px-3 py-1.5 hover:bg-white {{ $from->toDateString() === $rangeFrom && $to->toDateString() === $rangeTo ? 'bg-violet-300 font-medium' : 'bg-white/50' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    @error('from')
        <p role="alert" class="mb-4 rounded-2xl bg-rose-100 px-4 py-2.5 text-sm text-rose-700">{{ $message }}</p>
    @enderror
    @error('to')
        <p role="alert" class="mb-4 rounded-2xl bg-rose-100 px-4 py-2.5 text-sm text-rose-700">{{ $message }}</p>
    @enderror

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-6">
        <x-admin.stat label="Revenue" :value="$money($revenue)" hint="Food + delivery fees" />
        <x-admin.stat label="Delivery fees" :value="$money($deliveryFees)" hint="Included in revenue" />
        <x-admin.stat label="Orders" :value="$orderCount" />
        <x-admin.stat label="Average order" :value="$money($averageOrder)" />
        <x-admin.stat label="Items sold" :value="$itemsSold" />
        <x-admin.stat label="Canceled" :value="$canceledCount" hint="Not in revenue" />
    </div>

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold">Top dishes</h2>
            <ol class="mt-3 divide-y divide-neutral-100 text-sm">
                @forelse ($topDishes as $dish => $row)
                    <li class="flex items-center gap-3 py-2">
                        <span class="grid size-6 shrink-0 place-items-center rounded-full bg-neutral-900 text-[11px] font-semibold text-white">{{ $loop->iteration }}</span>
                        <span class="min-w-0 flex-1 truncate font-medium">{{ $dish }}</span>
                        <span class="text-neutral-500">{{ $row['quantity'] }} sold</span>
                        <span class="w-24 text-right font-semibold">{{ $money($row['revenue']) }}</span>
                    </li>
                @empty
                    <li class="py-6 text-center text-neutral-500">No sales in this period.</li>
                @endforelse
            </ol>
        </section>

        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold">Sales by category</h2>
            <ul class="mt-3 divide-y divide-neutral-100 text-sm">
                @forelse ($byCategory as $category => $row)
                    <li class="flex items-center gap-3 py-2">
                        <span class="min-w-0 flex-1 truncate font-medium">{{ $category }}</span>
                        <span class="text-neutral-500">{{ $row['quantity'] }} sold</span>
                        <span class="w-24 text-right font-semibold">{{ $money($row['revenue']) }}</span>
                    </li>
                @empty
                    <li class="py-6 text-center text-neutral-500">No sales in this period.</li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold">By order type</h2>
            <ul class="mt-3 divide-y divide-neutral-100 text-sm">
                @forelse ($byType as $type => $row)
                    <li class="flex items-center gap-3 py-2">
                        <span class="min-w-0 flex-1 truncate font-medium">{{ $type }}</span>
                        <span class="text-neutral-500">{{ $row['orders'] }} {{ \Illuminate\Support\Str::plural('order', $row['orders']) }}</span>
                        <span class="w-24 text-right font-semibold">{{ $money($row['revenue']) }}</span>
                    </li>
                @empty
                    <li class="py-6 text-center text-neutral-500">No sales in this period.</li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-2xl bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold">By cashier</h2>
            <ul class="mt-3 divide-y divide-neutral-100 text-sm">
                @forelse ($byCashier as $name => $row)
                    <li class="flex items-center gap-3 py-2">
                        <span class="min-w-0 flex-1 truncate font-medium">{{ $name }}</span>
                        <span class="text-neutral-500">{{ $row['orders'] }} {{ \Illuminate\Support\Str::plural('order', $row['orders']) }}</span>
                        <span class="w-24 text-right font-semibold">{{ $money($row['revenue']) }}</span>
                    </li>
                @empty
                    <li class="py-6 text-center text-neutral-500">No sales in this period.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <section class="mt-5 overflow-x-auto rounded-2xl bg-white p-4 shadow-sm">
        <h2 class="text-sm font-semibold">Day by day</h2>
        <table class="mt-3 w-full min-w-[32rem] text-left text-sm">
            <thead class="text-xs text-neutral-500">
                <tr class="border-b border-neutral-100">
                    <th class="py-2 font-medium">Date</th>
                    <th class="py-2 font-medium">Orders</th>
                    <th class="py-2 font-medium">Revenue</th>
                    <th class="w-1/2 py-2"><span class="sr-only">Chart</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @foreach ($days as $day)
                    <tr>
                        <td class="py-2 whitespace-nowrap">{{ $day['date']->format('D, M j') }}</td>
                        <td class="py-2">{{ $day['orders'] }}</td>
                        <td class="py-2 font-semibold">{{ $money($day['revenue']) }}</td>
                        <td class="py-2">
                            <div class="h-2.5 rounded-full bg-neutral-100">
                                <div class="h-2.5 rounded-full bg-violet-400" style="width: {{ round($day['revenue'] / $maxDayRevenue * 100) }}%"></div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
</x-layouts.admin>
