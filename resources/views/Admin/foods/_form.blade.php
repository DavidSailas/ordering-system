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

        <div class="pa" data-photo-adjuster>
            {{-- The real file input. Visually hidden; the adjuster fills it with the framed photo. --}}
            <input id="image" type="file" name="image" accept="image/png,image/jpeg,image/webp" class="pa-hidden-input" data-pa-input>

            {{-- Current photo (edit screen) --}}
            @if ($item->image_url)
                <div class="pa-current" data-pa-current>
                    <x-dish-image :item="$item" />
                    <button type="button" class="pa-btn pa-replace" data-pa-change>Replace photo</button>
                </div>
            @endif

            {{-- Empty state / drop zone --}}
            <label for="image" class="pa-drop" tabindex="0" data-pa-drop @if ($item->image_url) hidden @endif
                   onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); this.click(); }">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4m0 0 4 4m-4-4L8 8"/><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                <div>
                    <strong>Click to upload or drop a photo here</strong>
                    <span>JPG, PNG or WebP · you can crop it next</span>
                </div>
            </label>

            {{-- Adjuster (appears after a photo is chosen) --}}
            <div class="pa-editor">
                <div class="pa-stage" data-pa-stage>
                    <canvas tabindex="0" data-pa-canvas role="img"
                            aria-label="Photo preview. Drag to reposition, use arrow keys to move and plus or minus to zoom."></canvas>
                    <span class="pa-hint" aria-hidden="true">Drag to reposition</span>
                </div>

                <div class="pa-zoom">
                    <button type="button" class="pa-icon-btn" data-pa-zoom-out aria-label="Zoom out">−</button>
                    <input type="range" min="1" max="4" step="0.01" value="1" data-pa-zoom aria-label="Zoom">
                    <button type="button" class="pa-icon-btn" data-pa-zoom-in aria-label="Zoom in">+</button>
                    <output data-pa-zoom-label>100%</output>
                </div>

                <div class="pa-actions">
                    <button type="button" class="pa-btn" data-pa-rotate>Rotate</button>
                    <button type="button" class="pa-btn" data-pa-reset>Reset</button>
                    <button type="button" class="pa-btn" data-pa-change>Change photo</button>
                    <button type="button" class="pa-btn pa-btn--quiet" data-pa-discard>Discard</button>
                </div>
            </div>

            <p class="pa-note" data-pa-note hidden></p>
            <p class="pa-help">Shown in a 4:3 frame on the menu. What you see here is exactly what customers will see.</p>

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

<script src="{{ asset('photo-adjuster.js') }}?v={{ @filemtime(public_path('photo-adjuster.js')) }}" defer></script>
