@php($logo = config('restaurant.logo_path'))
<link rel="icon" type="image/png" href="{{ $logo ? asset('storage/'.$logo) : asset('favicon.png') }}">
<link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
