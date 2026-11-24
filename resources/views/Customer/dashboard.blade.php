@use('App\Enums\OrderStatus')
@use('App\Enums\OrderType')
@use('Illuminate\Support\Str')

@php
    $currency = config('restaurant.currency');

    $query = fn (array $overrides = []): array => array_filter(array_merge([
        'type' => $type?->value,
        'category' => $activeCategory?->slug,
        'q' => $search,
    ], $overrides));
@endphp

<x-layouts.app title="Dashboard">
    <div id="pos" class="contents" data-currency="{{ $currency }}" data-store-url="{{ route('orders.store') }}">
        <main class="flex min-h-0 flex-col gap-5 rounded-3xl bg-linear-to-br from-lime-200 via-lime-100 to-sky-200 p-5">
            @error('status')
                <p class="rounded-2xl bg-rose-100 px-4 py-2 text-sm text-rose-700">{{ $message }}</p>
            @enderror

            <section id="order-line" class="shrink-0">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-lg font-semibold">Order Line</h2>

                    <div class="flex items-center gap-1 rounded-full bg-white/50 p-1 text-sm">
                        @foreach (OrderType::cases() as $case)
                            @php($isActive = $type === $case)
                            <a
                                href="{{ route('dashboard', $query(['type' => $case->value])) }}"
                                class="flex items-center gap-2 rounded-full px-4 py-1.5 {{ $isActive ? 'bg-violet-300 font-medium' : 'hover:bg-white/60' }}"
                            >
                                {{ $case->label() }}
                                @if ($isActive)
                                    <span class="grid size-6 place-items-center rounded-full bg-white text-xs">{{ $orderCounts[$case->value] ?? 0 }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>

                    <a
                        href="{{ route('dashboard', $query(['type' => null])) }}"
                        class="ml-auto flex items-center gap-1 rounded-full px-4 py-1.5 text-sm {{ $type ? 'bg-white/50 hover:bg-white/80' : 'bg-violet-300 font-medium' }}"
                    >
                        All Orders ({{ $orderCounts->sum() }})
                        <x-icon name="chevron-right" class="size-3.5" />
                    </a>
                </div>

                <div class="mt-3 flex gap-3 overflow-x-auto pb-1">
                    @forelse ($orders as $order)
                        @php
                            $next = $order->status->next();
                            $statusClasses = match ($order->status) {
                                OrderStatus::AwaitingPayment => 'bg-amber-200 text-amber-950',
                                OrderStatus::InProgress => 'bg-lime-300 text-lime-950',
                                OrderStatus::ReadyToServe => 'bg-sky-300 text-sky-950',
                                OrderStatus::Canceled => 'bg-rose-300 text-rose-950',
                                OrderStatus::Completed => 'bg-neutral-100 text-neutral-500',
                            };
                        @endphp
                        <article class="w-52 shrink-0 rounded-2xl bg-white p-3 shadow-sm">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="truncate text-sm font-semibold">{{ $order->customer_name }}</h3>
                                <span class="text-[11px] text-neutral-400">#{{ $order->number }}</span>
                            </div>

                            <div class="mt-1 flex justify-between text-[11px] text-neutral-500">
                                <span>{{ (int) $order->items_count }} {{ Str::plural('Item', (int) $order->items_count) }}</span>
                                <span>{{ $order->created_at->diffForHumans(short: true) }}</span>
                            </div>

                            <div class="mt-3 flex items-center gap-1.5">
                                <span class="truncate rounded-full bg-sky-100 px-2.5 py-1 text-[11px] font-medium">
                                    {{ $order->diningTable?->name ?? $order->type->label() }}
                                </span>

                                @if ($next)
                                    <form method="POST" action="{{ route('orders.status', $order) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $next->value }}">
                                        <button type="submit" title="Mark as {{ $next->label() }}" class="whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-medium hover:opacity-80 {{ $statusClasses }}">
                                            {{ $order->status->label() }}
                                        </button>
                                    </form>
                                @else
                                    <span class="whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-medium {{ $statusClasses }}">
                                        {{ $order->status->label() }}
                                    </span>
                                @endif

                                @unless ($order->status->isFinal())
                                    <form method="POST" action="{{ route('orders.status', $order) }}" onsubmit="return confirm('Cancel order #{{ $order->number }}?')" class="ml-auto">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ OrderStatus::Canceled->value }}">
                                        <button type="submit" title="Cancel order" aria-label="Cancel order #{{ $order->number }}" class="grid size-5 place-items-center rounded-full text-neutral-400 hover:bg-rose-100 hover:text-rose-600">
                                            <x-icon name="x" class="size-3" />
                                        </button>
                                    </form>
                                @endunless
                            </div>
                        </article>
                    @empty
                        <p class="rounded-2xl bg-white/60 px-4 py-6 text-sm text-neutral-500">No orders yet. Place the first one from the menu below.</p>
                    @endforelse
                </div>
            </section>

            <section id="menu" class="flex min-h-0 flex-1 flex-col">
                <div class="flex items-center gap-3">
                    <h2 class="shrink-0 text-lg font-semibold">Explore Menu</h2>

                    <nav class="flex min-w-0 items-center gap-1 overflow-x-auto rounded-full bg-white/50 p-1 text-sm">
                        <a
                            href="{{ route('dashboard', $query(['category' => null])) }}"
                            class="flex shrink-0 items-center gap-2 rounded-full px-4 py-1.5 {{ $activeCategory ? 'hover:bg-white/60' : 'bg-violet-300 font-medium' }}"
                        >
                            All
                            @unless ($activeCategory)
                                <span class="grid size-6 place-items-center rounded-full bg-white text-xs">{{ $allItemsCount }}</span>
                            @endunless
                        </a>

                        @foreach ($categories as $category)
                            @php($isActive = $activeCategory?->is($category))
                            <a
                                href="{{ route('dashboard', $query(['category' => $category->slug])) }}"
                                class="flex shrink-0 items-center gap-2 rounded-full px-4 py-1.5 {{ $isActive ? 'bg-violet-300 font-medium' : 'hover:bg-white/60' }}"
                            >
                                {{ $category->name }}
                                @if ($isActive)
                                    <span class="grid size-6 place-items-center rounded-full bg-white text-xs">{{ $category->menu_items_count }}</span>
                                @endif
                            </a>
                        @endforeach
                    </nav>
                </div>

                @if ($search !== '')
                    <p class="mt-3 text-sm text-neutral-600">
                        Results for “{{ $search }}” ·
                        <a href="{{ route('dashboard', $query(['q' => null])) }}" class="font-medium underline underline-offset-2">Clear search</a>
                    </p>
                @endif

                <div class="mt-4 grid min-h-0 flex-1 content-start gap-3 overflow-y-auto pr-1 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse ($menuItems as $item)
                        <article
                            class="group flex gap-3 rounded-2xl bg-white p-2 shadow-sm"
                            data-menu-item
                            data-id="{{ $item->id }}"
                            data-name="{{ $item->name }}"
                            data-price="{{ $item->price }}"
                            data-active="false"
                        >
                            @if ($item->image_url)
                                <img src="{{ $item->image_url }}" alt="{{ $item->name }}" loading="lazy" class="size-24 shrink-0 rounded-xl object-cover">
                            @else
                                <div class="grid size-24 shrink-0 place-items-center rounded-xl bg-linear-to-br from-lime-200 to-violet-200 text-3xl font-semibold text-neutral-700">
                                    {{ Str::upper(Str::substr($item->name, 0, 1)) }}
                                </div>
                            @endif

                            <div class="flex min-w-0 flex-1 flex-col justify-between py-1">
                                <div>
                                    <div class="flex items-baseline justify-between gap-2 text-xs">
                                        <span class="truncate text-neutral-400">{{ $item->category->name }}</span>
                                        <span class="font-semibold">{{ $currency }}{{ number_format($item->price, 2) }}</span>
                                    </div>
                                    <h3 class="mt-1 truncate text-sm font-semibold">{{ $item->name }}</h3>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <button type="button" data-action="dec" aria-label="Remove one {{ $item->name }}" class="grid size-6 place-items-center rounded-full bg-neutral-200 text-neutral-400 group-data-[active=true]:bg-neutral-900 group-data-[active=true]:text-white">
                                            <x-icon name="minus" class="size-3" />
                                        </button>
                                        <span data-qty class="w-5 text-center text-sm font-medium text-neutral-400 group-data-[active=true]:text-neutral-900">0</span>
                                        <button type="button" data-action="inc" aria-label="Add one {{ $item->name }}" class="grid size-6 place-items-center rounded-full bg-neutral-200 text-neutral-400 group-data-[active=true]:bg-neutral-900 group-data-[active=true]:text-white">
                                            <x-icon name="plus" class="size-3" />
                                        </button>
                                    </div>

                                    <button type="button" data-action="add" aria-label="Add {{ $item->name }} to order" class="grid size-8 place-items-center rounded-full bg-violet-300 hover:bg-violet-400 group-data-[active=true]:hidden">
                                        <x-icon name="plus" class="size-4" />
                                    </button>
                                    <button type="button" data-action="remove" aria-label="Remove {{ $item->name }} from order" class="hidden size-8 place-items-center rounded-full bg-neutral-100 hover:bg-rose-100 group-data-[active=true]:grid">
                                        <x-icon name="x" class="size-4" />
                                    </button>
                                </div>
                            </div>
                        </article>
                    @empty
                        <p class="col-span-full rounded-2xl bg-white/60 px-4 py-6 text-sm text-neutral-500">
                            No dishes match your filters.
                        </p>
                    @endforelse
                </div>
            </section>
        </main>

        <aside class="flex min-h-0 flex-col gap-3 rounded-3xl bg-violet-200 p-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Order Details</h2>
                <button type="button" data-clear class="text-xs font-medium underline underline-offset-2">Cancel</button>
            </div>

            <div class="flex rounded-full bg-white/60 p-1 text-xs">
                @foreach (OrderType::cases() as $case)
                    <label class="flex-1 cursor-pointer rounded-full px-3 py-1.5 text-center font-medium has-[:checked]:bg-neutral-900 has-[:checked]:text-white">
                        <input type="radio" name="type" value="{{ $case->value }}" class="sr-only" @checked($loop->first)>
                        {{ $case->label() }}
                    </label>
                @endforeach
            </div>

            <div class="space-y-2">
                <input
                    type="text"
                    data-customer
                    maxlength="100"
                    placeholder="Customer Name"
                    aria-label="Customer name"
                    class="w-full rounded-2xl border-0 bg-white/60 px-4 py-3 text-sm placeholder:text-neutral-500 focus:outline-none focus:ring-2 focus:ring-violet-400"
                >

                <div data-table-field>
                    <select data-table aria-label="Table" class="w-full rounded-2xl border-0 bg-white/60 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400">
                        <option value="">Select Table</option>
                        @foreach ($tables as $table)
                            @php($isOccupied = $occupiedTableIds->contains($table->id))
                            <option value="{{ $table->id }}" @disabled($isOccupied)>
                                {{ $table->name }} · {{ $table->seats }} seats{{ $isOccupied ? ' (occupied)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-between text-xs text-neutral-600">
                <span class="font-medium">New order</span>
                <span><span data-cart-count>0</span> items</span>
            </div>

            <div class="max-h-96 min-h-0 flex-1 overflow-y-auto lg:max-h-none">
                <p data-cart-empty class="rounded-2xl bg-white/50 px-4 py-6 text-center text-sm text-neutral-500">
                    Pick dishes from the menu to start an order.
                </p>
                <ul data-cart-items class="space-y-2"></ul>
            </div>

            <div>
                <button type="button" data-toggle-notes class="rounded-full bg-white/60 px-3 py-1.5 text-xs font-medium hover:bg-white/80">
                    + Cooking Request
                </button>
                <textarea
                    data-notes
                    hidden
                    maxlength="500"
                    rows="2"
                    placeholder="e.g. no onions, extra spicy"
                    aria-label="Cooking request"
                    class="mt-2 w-full rounded-2xl border-0 bg-white/60 px-4 py-3 text-sm placeholder:text-neutral-500 focus:outline-none focus:ring-2 focus:ring-violet-400"
                ></textarea>
            </div>

            <div class="flex items-center justify-between rounded-2xl bg-white/60 px-4 py-3 text-sm">
                <span>Total Payment</span>
                <span data-cart-total class="font-semibold">{{ $currency }}0.00</span>
            </div>

            <p data-cart-error hidden role="alert" class="rounded-2xl bg-rose-100 px-4 py-2 text-xs text-rose-700"></p>

            <button type="button" data-place-order disabled class="w-full rounded-full bg-neutral-900 py-3 text-sm font-semibold text-white transition hover:bg-neutral-700 disabled:cursor-not-allowed disabled:opacity-40">
                Place Order
            </button>
        </aside>

        <template id="cart-row-template">
            <li data-row class="rounded-2xl bg-white p-3 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <h3 data-name class="text-sm font-semibold"></h3>
                    <button type="button" data-action="remove" aria-label="Remove item" class="grid size-6 shrink-0 place-items-center rounded-full bg-violet-100 hover:bg-rose-100">
                        <x-icon name="x" class="size-3" />
                    </button>
                </div>

                <div class="mt-2 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <button type="button" data-action="dec" aria-label="Remove one" class="grid size-6 place-items-center rounded-full bg-neutral-900 text-white">
                            <x-icon name="minus" class="size-3" />
                        </button>
                        <span data-qty class="w-5 text-center text-sm font-medium"></span>
                        <button type="button" data-action="inc" aria-label="Add one" class="grid size-6 place-items-center rounded-full bg-neutral-900 text-white">
                            <x-icon name="plus" class="size-3" />
                        </button>
                    </div>

                    <span data-line-total class="rounded-full bg-sky-100 px-4 py-1 text-sm font-medium"></span>
                </div>
            </li>
        </template>
    </div>
</x-layouts.app>
