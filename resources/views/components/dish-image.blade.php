@props(['item', 'thumb' => false])

{{-- Every dish photo is shown in the same 4:3 frame, with the same placeholder. --}}
<div {{ $attributes->class(['dish-img', 'dish-img--thumb' => $thumb]) }}>
    @if ($item->image_url)
        <img src="{{ $item->image_url }}" alt="{{ $item->name }}" loading="lazy" width="800" height="600">
    @else
        <x-icon name="utensils" class="dish-img-ph" />
    @endif
</div>
