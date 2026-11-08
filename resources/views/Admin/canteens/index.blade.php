@php($currency = config('restaurant.currency'))
<x-layouts.admin title="Canteens">
    <div class="ui-page-head">
        <div>
            <h1>Canteens</h1>
            <p>Every canteen has its own menu, orders, staff, logo and payment accounts. They never mix.</p>
        </div>
    </div>

    <div class="canteen-grid">
        @foreach ($canteens as $canteen)
            <article class="ui-card ui-card-pad canteen-card" style="--brand: {{ $canteen->accent }}">
                <div class="canteen-card-head">
                    <x-logo :canteen="$canteen" :size="52" />
                    <div>
                        <h2>{{ $canteen->name }}</h2>
                        <p>{{ $canteen->tagline ?: 'No description yet' }}</p>
                    </div>
                    <span class="ui-badge {{ $canteen->is_open ? 'ui-badge--ok' : 'ui-badge--bad' }}">{{ $canteen->is_open ? 'Open' : 'Closed' }}</span>
                </div>

                <dl class="canteen-stats">
                    <div><dt>Orders today</dt><dd>{{ $canteen->stats['orders_today'] }}</dd></div>
                    <div><dt>Active now</dt><dd>{{ $canteen->stats['active'] }}</dd></div>
                    <div><dt>Sales today</dt><dd>{{ $currency }}{{ number_format($canteen->stats['sales_today'], 0) }}</dd></div>
                    <div><dt>Dishes</dt><dd>{{ $canteen->stats['dishes'] }}</dd></div>
                    <div><dt>Staff</dt><dd>{{ $canteen->stats['staff'] }}</dd></div>
                </dl>

                <form method="POST" action="{{ route('admin.canteens.switch', $canteen) }}">
                    @csrf
                    <button type="submit" class="ui-btn {{ $canteen->id === $managingId ? 'ui-btn--soft' : 'ui-btn--primary' }} ui-btn--block">
                        {{ $canteen->id === $managingId ? 'Currently managing — open dashboard' : 'Manage this canteen' }}
                    </button>
                </form>
            </article>
        @endforeach
    </div>

    <section class="ui-card ui-card-pad" style="margin-top:20px;max-width:560px">
        <div class="ui-card-head"><div><h2>Add a canteen</h2><p>You can set the logo, payment accounts and owner right after.</p></div></div>
        <form method="POST" action="{{ route('admin.canteens.store') }}" class="ui-form">
            @csrf
            <div class="ui-form-grid">
                <div class="ui-field">
                    <label for="new-name">Canteen name</label>
                    <input id="new-name" name="name" required maxlength="80" value="{{ old('name') }}">
                    @error('name')<p class="ui-error">{{ $message }}</p>@enderror
                </div>
                <div class="ui-field">
                    <label for="new-accent">Brand colour</label>
                    <input id="new-accent" type="color" name="accent" value="{{ old('accent', '#d62d1a') }}" style="height:44px;padding:4px">
                </div>
            </div>
            <div><button type="submit" class="ui-btn ui-btn--primary"><x-icon name="plus" class="ui-i ui-i--sm" />Create canteen</button></div>
        </form>
    </section>
</x-layouts.admin>
