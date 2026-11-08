@props(['size' => 40, 'canteen' => null])

{{-- A canteen's logo: the uploaded image, or the built-in burger logo (public/logo.png). --}}
@php($logo = $canteen ? $canteen->logo_path : config('restaurant.logo_path'))
@php($logoName = $canteen->name ?? config('restaurant.name'))
<span {{ $attributes->class(['brand-logo']) }} style="--logo-size: {{ (int) $size }}px{{ $canteen ? '; --brand: '.$canteen->accent : '' }}">
    @if ($logo)
        <img src="{{ asset('storage/'.$logo) }}" alt="{{ $logoName }} logo">
    @else
        <img src="{{ asset('logo.png') }}" alt="{{ $logoName }} logo" class="brand-logo-default">
    @endif
</span>
