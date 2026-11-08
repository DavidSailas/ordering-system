@use('Illuminate\Support\Str')

@php
    $currency = config('restaurant.currency');

    $query = fn (array $overrides = []): array => array_filter(array_merge([
        'category' => $activeCategory?->slug,
        'q' => $search,
    ], $overrides));
@endphp

<x-layouts.admin title="Menu">
    <x-admin.page-title title="Menu" subtitle="Everything the cashiers can sell">
        <form method="GET" action="{{ route('admin.foods.index') }}" class="flex items-center gap-2 rounded-full bg-white/60 px-4 py-2">
            @if ($activeCategory)
                <input type="hidden" name="category" value="{{ $activeCategory->slug }}">
            @endif
            <x-icon name="search" class="size-4 text-neutral-500" />
            <input type="search" name="q" value="{{ $search }}" placeholder="Search dishes" class="w-36 border-0 bg-transparent p-0 text-sm placeholder:text-neutral-500 focus:outline-none sm:w-48">
        </form>

        <a href="{{ route('admin.foods.create') }}" class="flex items-center gap-1.5 rounded-full bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-700">
            <x-icon name="plus" class="size-4" />
            Add dish
        </a>
    </x-admin.page-title>

    <nav class="mb-5 flex items-center gap-1 overflow-x-auto rounded-full bg-white/50 p-1 text-sm">
        <a href="{{ route('admin.foods.index', $query(['category' => null])) }}" class="flex shrink-0 items-center gap-2 rounded-full px-4 py-1.5 {{ $activeCategory ? 'hover:bg-white/60' : 'bg-violet-300 font-medium' }}">
            All
            <span class="grid size-6 place-items-center rounded-full bg-white text-xs">{{ $totalCount }}</span>
        </a>

        @foreach ($categories as $category)
            @php($isActive = $activeCategory?->is($category))
            <a href="{{ route('admin.foods.index', $query(['category' => $category->slug])) }}" class="flex shrink-0 items-center gap-2 rounded-full px-4 py-1.5 {{ $isActive ? 'bg-violet-300 font-medium' : 'hover:bg-white/60' }}">
                {{ $category->name }}
                <span class="grid size-6 place-items-center rounded-full bg-white text-xs">{{ $category->menu_items_count }}</span>
            </a>
        @endforeach
    </nav>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($items as $item)
            <article class="flex gap-3 rounded-2xl bg-white p-2 shadow-sm {{ $item->is_available ? '' : 'opacity-70' }}">
                <x-dish-image :item="$item" thumb />

                <div class="flex min-w-0 flex-1 flex-col justify-between py-1">
                    <div>
                        <div class="flex items-baseline justify-between gap-2 text-xs">
                            <span class="truncate text-neutral-400">{{ $item->category->name }}</span>
                            <span class="font-semibold">{{ $currency }}{{ number_format($item->price, 2) }}</span>
                        </div>
                        <h3 class="mt-1 truncate text-sm font-semibold">{{ $item->name }}</h3>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <form method="POST" action="{{ route('admin.foods.availability', $item) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" title="Click to toggle" class="rounded-full px-3 py-1 text-[11px] font-medium {{ $item->is_available ? 'bg-lime-300 text-lime-950 hover:bg-lime-400' : 'bg-neutral-200 text-neutral-600 hover:bg-neutral-300' }}">
                                {{ $item->is_available ? 'Available' : 'Sold out' }}
                            </button>
                        </form>

                        <a href="{{ route('admin.foods.edit', $item) }}" aria-label="Edit {{ $item->name }}" class="ml-auto grid size-8 place-items-center rounded-full bg-violet-300 hover:bg-violet-400">
                            <x-icon name="pencil" class="size-4" />
                        </a>

                        <form method="POST" action="{{ route('admin.foods.destroy', $item) }}" data-confirm="Delete {{ $item->name }} from the menu?" data-confirm-title="Delete this item?" data-confirm-ok="Yes, delete">
                            @csrf
                            @method('DELETE')
                            <button type="submit" aria-label="Delete {{ $item->name }}" class="grid size-8 place-items-center rounded-full bg-neutral-100 hover:bg-rose-100 hover:text-rose-600">
                                <x-icon name="trash" class="size-4" />
                            </button>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <p class="col-span-full rounded-2xl bg-white/60 px-4 py-8 text-center text-sm text-neutral-500">
                No dishes found. <a href="{{ route('admin.foods.create') }}" class="font-medium underline underline-offset-2">Add the first one</a>.
            </p>
        @endforelse
    </div>

    <div class="mt-5">
        {{ $items->links() }}
    </div>
</x-layouts.admin>
