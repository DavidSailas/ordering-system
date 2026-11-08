@php($currency = config('restaurant.currency'))

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
    @csrf
    @if ($item->exists)
        @method('PUT')
    @endif

    <div class="space-y-4 rounded-2xl bg-white p-5 shadow-sm">
        <x-admin.field name="name" label="Dish name" :value="$item->name" maxlength="255" required autofocus />

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="category_id" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-neutral-600">Category</label>
                <select id="category_id" name="category_id" required class="w-full rounded-xl border-0 bg-white px-4 py-2.5 text-sm shadow-sm ring-1 ring-violet-200 focus:outline-none focus:ring-2 focus:ring-violet-400">
                    <option value="">Select category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('category_id', $item->category_id) === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id')
                    <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                @enderror
                @if ($categories->isEmpty())
                    <p class="mt-1.5 text-xs text-neutral-500">
                        No categories yet. <a href="{{ route('admin.categories.index') }}" class="font-medium underline">Create one first</a>.
                    </p>
                @endif
            </div>

            <x-admin.field name="price" :label="'Price ('.$currency.')'" type="number" :value="$item->price" step="0.01" min="0" max="99999.99" required />
        </div>

        <div>
            <label for="description" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-neutral-600">Description (optional)</label>
            <textarea id="description" name="description" rows="3" maxlength="1000" class="w-full rounded-xl border-0 bg-white px-4 py-2.5 text-sm shadow-sm ring-1 ring-violet-200 placeholder:text-neutral-400 focus:outline-none focus:ring-2 focus:ring-violet-400">{{ old('description', $item->description) }}</textarea>
            @error('description')
                <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="is_available" value="0">
            <input type="checkbox" name="is_available" value="1" class="size-4 rounded accent-violet-500" @checked(old('is_available', $item->is_available))>
            Available to order (untick to mark as sold out)
        </label>
    </div>

    <aside class="space-y-4 self-start rounded-3xl bg-violet-200 p-4">
        <h2 class="text-lg font-semibold">Dish photo</h2>

        <x-dish-image :item="$item" />

        <div>
            <input id="image" type="file" name="image" accept="image/png,image/jpeg,image/webp" class="w-full text-sm file:mr-3 file:rounded-full file:border-0 file:bg-neutral-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-neutral-700">
            <p class="mt-1.5 text-xs text-neutral-600">JPG, PNG or WebP, up to 4 MB, at least 400 × 300 px. Photos are cropped to 4:3 so every dish looks the same.</p>
            @error('image')
                <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        @if ($item->image_url)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remove_image" value="1" class="size-4 rounded accent-violet-500">
                Remove current photo
            </label>
        @endif

        <div class="flex gap-2 pt-2">
            <button type="submit" class="flex-1 rounded-full bg-neutral-900 py-3 text-sm font-semibold text-white hover:bg-neutral-700">
                {{ $item->exists ? 'Save changes' : 'Add to menu' }}
            </button>
            <a href="{{ route('admin.foods.index') }}" class="rounded-full bg-white/70 px-5 py-3 text-sm font-medium hover:bg-white">Cancel</a>
        </div>
    </aside>
</form>
