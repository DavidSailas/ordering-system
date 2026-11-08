@php($fieldErrors = collect($errors->getMessages())->except(['category', 'table', 'staff', 'status'])->flatten()->unique())

<x-layouts.admin title="Categories">
    <x-admin.page-title title="Categories" subtitle="The tabs shown above the menu on the cashier screen" />

    @if ($fieldErrors->isNotEmpty())
        <ul role="alert" class="mb-4 space-y-1 rounded-2xl bg-rose-100 px-4 py-2.5 text-sm text-rose-700">
            @foreach ($fieldErrors as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('admin.categories.store') }}" class="mb-5 flex flex-wrap items-center gap-2 rounded-2xl bg-violet-200 p-3">
        @csrf
        <input type="text" name="name" value="{{ old('name') }}" maxlength="100" required placeholder="New category name" aria-label="Category name" class="min-w-0 flex-1 rounded-xl border-0 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400">
        <input type="number" name="sort_order" min="0" max="999" placeholder="Order" aria-label="Sort order" class="w-24 rounded-xl border-0 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400">
        <button type="submit" class="flex items-center gap-1.5 rounded-full bg-neutral-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-neutral-700">
            <x-icon name="plus" class="size-4" />
            Add category
        </button>
    </form>

    <ul class="space-y-2">
        @forelse ($categories as $category)
            <li class="flex flex-wrap items-center gap-2 rounded-2xl bg-white p-3 shadow-sm">
                <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                    @csrf
                    @method('PUT')
                    <input type="text" name="name" value="{{ $category->name }}" maxlength="100" required aria-label="Category name" class="min-w-0 flex-1 rounded-xl border-0 bg-neutral-100 px-4 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-violet-400">
                    <input type="number" name="sort_order" value="{{ $category->sort_order }}" min="0" max="999" aria-label="Sort order" class="w-20 rounded-xl border-0 bg-neutral-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400">
                    <span class="w-20 text-xs text-neutral-500">{{ $category->menu_items_count }} {{ \Illuminate\Support\Str::plural('dish', $category->menu_items_count) }}</span>
                    <button type="submit" class="flex items-center gap-1.5 rounded-full bg-violet-300 px-4 py-2 text-sm font-medium hover:bg-violet-400">
                        <x-icon name="check" class="size-4" />
                        Save
                    </button>
                </form>

                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" data-confirm="Delete the {{ $category->name }} category?" data-confirm-title="Delete this item?" data-confirm-ok="Yes, delete">
                    @csrf
                    @method('DELETE')
                    <button type="submit" aria-label="Delete {{ $category->name }}" class="grid size-9 place-items-center rounded-full bg-neutral-100 hover:bg-rose-100 hover:text-rose-600">
                        <x-icon name="trash" class="size-4" />
                    </button>
                </form>
            </li>
        @empty
            <li class="rounded-2xl bg-white/60 px-4 py-8 text-center text-sm text-neutral-500">No categories yet. Add one above to start building the menu.</li>
        @endforelse
    </ul>
</x-layouts.admin>
