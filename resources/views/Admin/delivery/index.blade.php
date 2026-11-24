<x-layouts.admin title="Delivery & fees">
    @php($currency = config('restaurant.currency', '₱'))
    <style>
        .dz-card { padding: 20px; margin-bottom: 16px; border-radius: 16px; background: #fff; box-shadow: 0 1px 3px rgba(20, 24, 40, .08); }
        .dz-card h2 { margin: 0 0 4px; font-size: 16px; }
        .dz-sub { margin: 0 0 14px; color: #6b7385; font-size: 13.5px; }
        .dz-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .dz-pill { display: inline-flex; padding: 5px 12px; border-radius: 99px; font-size: 13px; font-weight: 700; }
        .dz-pill--on { background: #dcfce7; color: #14532d; }
        .dz-pill--off { background: #f3f4f6; color: #4b5563; }
        .dz-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 360px), 1fr)); gap: 16px; }
        .dz-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .dz-fields .dz-wide { grid-column: 1 / -1; }
        .dz-fields label, .dz-label { display: block; margin-bottom: 4px; color: #4b5563; font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .dz-fields input[type=text], .dz-fields input[type=number] { width: 100%; padding: 10px 12px; border: 1px solid #d9dce3; border-radius: 10px; background: #fff; font-size: 14px; }
        .dz-check { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; text-transform: none; letter-spacing: 0; }
        .dz-check input { width: 18px; height: 18px; }
        .dz-home { padding: 12px; border-radius: 12px; background: #fff7ed; }
        .dz-home small { display: block; margin-top: 6px; color: #7c5a3b; font-size: 12.5px; text-transform: none; letter-spacing: 0; font-weight: 500; }
        .dz-actions { display: flex; gap: 8px; margin-top: 14px; flex-wrap: wrap; }
        .dz-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: 8px 16px; border: 1px solid #e3e6ec; border-radius: 99px; background: #fff; color: inherit; font: inherit; font-size: 13.5px; font-weight: 600; cursor: pointer; }
        .dz-btn:hover { background: #f4f5f8; }
        .dz-btn--primary { border-color: var(--brand, #e25806); background: var(--brand, #e25806); color: #fff; }
        .dz-btn--primary:hover { background: var(--brand-600, #c94d05); }
        .dz-btn--danger:hover { background: #fee2e2; color: #b91c1c; }
        .dz-preview { margin-top: 14px; padding-top: 12px; border-top: 1px solid #eceff3; }
        .dz-preview summary { cursor: pointer; color: #4b5563; font-size: 13px; font-weight: 700; }
        .dz-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
        .dz-chip { padding: 4px 10px; border-radius: 99px; background: #f1f2f6; font-size: 12.5px; }
        .dz-chip b { font-weight: 700; }
        .dz-badge { display: inline-block; margin-left: 6px; padding: 2px 8px; border-radius: 99px; background: #ffedd5; color: #9a3412; font-size: 11.5px; font-weight: 700; vertical-align: middle; }
        .dz-badge--off { background: #f3f4f6; color: #6b7280; }
        .dz-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        .dz-table th { padding: 8px 10px; color: #6b7385; font-size: 12px; font-weight: 600; text-align: left; border-bottom: 1px solid #eceff3; }
        .dz-table td { padding: 10px; border-bottom: 1px solid #f1f2f6; vertical-align: top; }
        .dz-empty { padding: 22px; text-align: center; color: #6b7385; font-size: 14px; }
        @media (max-width: 520px) { .dz-fields { grid-template-columns: minmax(0, 1fr); } }
    </style>

    <x-admin.page-title title="Delivery & fees" subtitle="The buildings your student runners deliver to, how many floors each has, and what each delivery costs." />

    @if ($errors->any())
        <div class="ui-alert ui-alert--bad mb-4" role="alert"><x-icon name="alert" class="ui-i" />{{ $errors->first() }}</div>
    @endif

    <section class="dz-card">
        <div class="dz-top">
            <div>
                <h2>Delivery service</h2>
                <p class="dz-sub" style="margin:0">Turn this off when no runner is on duty. Customers can still order for pickup.</p>
            </div>
            <div class="dz-top">
                <span class="dz-pill {{ $canteen->delivery_enabled ? 'dz-pill--on' : 'dz-pill--off' }}">{{ $canteen->delivery_enabled ? 'ON' : 'OFF' }}</span>
                <form method="POST" action="{{ route('admin.delivery.settings') }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="delivery_enabled" value="{{ $canteen->delivery_enabled ? 0 : 1 }}">
                    <button type="submit" class="dz-btn {{ $canteen->delivery_enabled ? 'dz-btn--danger' : 'dz-btn--primary' }}">{{ $canteen->delivery_enabled ? 'Turn off delivery' : 'Turn on delivery' }}</button>
                </form>
            </div>
        </div>
        <p class="dz-sub" style="margin:14px 0 0"><b>How the fee works:</b> base fee + the fee per floor for every floor the runner travels. Inside the canteen's own building the runner travels from the canteen's floor (up or down). In any other building the runner travels from the ground floor. Delivery orders must be paid in advance with GCash or Maya.</p>
    </section>

    <div class="dz-grid">
        @forelse ($zones as $zone)
            <section class="dz-card" style="margin:0">
                <h2>
                    {{ $zone->name }}
                    @if ($zone->is_home)<span class="dz-badge">Canteen is here · floor {{ $zone->home_floor }}</span>@endif
                    @unless ($zone->is_active)<span class="dz-badge dz-badge--off">Hidden</span>@endunless
                </h2>
                <p class="dz-sub">{{ $zone->floors }} {{ \Illuminate\Support\Str::plural('floor', $zone->floors) }}</p>

                <form method="POST" action="{{ route('admin.delivery.zones.update', $zone) }}" id="zone-{{ $zone->id }}">
                    @csrf @method('PUT')
                    <div class="dz-fields">
                        <div class="dz-wide"><label for="name-{{ $zone->id }}">Building name</label><input id="name-{{ $zone->id }}" type="text" name="name" value="{{ $zone->name }}" maxlength="60" required></div>
                        <div><label for="floors-{{ $zone->id }}">Number of floors</label><input id="floors-{{ $zone->id }}" type="number" name="floors" value="{{ $zone->floors }}" min="1" max="60" required></div>
                        <div><label for="base-{{ $zone->id }}">Base fee ({{ $currency }})</label><input id="base-{{ $zone->id }}" type="number" name="base_fee" value="{{ $zone->base_fee }}" min="0" max="500" step="0.01" required></div>
                        <div><label for="per-{{ $zone->id }}">Extra per floor ({{ $currency }})</label><input id="per-{{ $zone->id }}" type="number" name="per_floor_fee" value="{{ $zone->per_floor_fee }}" min="0" max="100" step="0.01" required></div>
                        <div class="dz-home dz-wide">
                            <label class="dz-check"><input type="checkbox" name="is_home" value="1" @checked($zone->is_home)> The canteen is in this building</label>
                            <div style="margin-top:8px"><label for="home-{{ $zone->id }}">Canteen floor</label><input id="home-{{ $zone->id }}" type="number" name="home_floor" value="{{ $zone->home_floor }}" min="1" max="60" placeholder="e.g. 6"></div>
                            <small>Fees here are counted from the canteen's floor, so a nearby floor costs less.</small>
                        </div>
                        <div class="dz-wide"><label class="dz-check"><input type="checkbox" name="is_active" value="1" @checked($zone->is_active)> Customers can choose this building</label></div>
                    </div>
                </form>

                <div class="dz-actions">
                    <button type="submit" form="zone-{{ $zone->id }}" class="dz-btn dz-btn--primary">Save changes</button>
                    <form method="POST" action="{{ route('admin.delivery.zones.destroy', $zone) }}" data-confirm="Remove {{ $zone->name }}? Customers will no longer be able to choose it. Past orders keep the fee they were charged." data-confirm-title="Remove this building?" data-confirm-ok="Yes, remove">
                        @csrf @method('DELETE')
                        <button type="submit" class="dz-btn dz-btn--danger">Remove</button>
                    </form>
                </div>

                <details class="dz-preview">
                    <summary>Fee for each floor</summary>
                    <div class="dz-chips">
                        @foreach ($zone->feeTable() as $floor => $fee)
                            <span class="dz-chip">Floor {{ $floor }}: <b>{{ $currency }}{{ number_format($fee, 2) }}</b></span>
                        @endforeach
                    </div>
                </details>
            </section>
        @empty
            <p class="dz-card dz-empty" style="grid-column:1/-1">No buildings yet. Add one below so customers can order delivery.</p>
        @endforelse
    </div>

    <section class="dz-card" style="margin-top:16px">
        <h2>Add a building</h2>
        <p class="dz-sub">For example “Library” with 3 floors.</p>
        <form method="POST" action="{{ route('admin.delivery.zones.store') }}">
            @csrf
            <div class="dz-fields">
                <div class="dz-wide"><label for="new-name">Building name</label><input id="new-name" type="text" name="name" value="{{ old('name') }}" maxlength="60" required></div>
                <div><label for="new-floors">Number of floors</label><input id="new-floors" type="number" name="floors" value="{{ old('floors', 3) }}" min="1" max="60" required></div>
                <div><label for="new-base">Base fee ({{ $currency }})</label><input id="new-base" type="number" name="base_fee" value="{{ old('base_fee', 25) }}" min="0" max="500" step="0.01" required></div>
                <div><label for="new-per">Extra per floor ({{ $currency }})</label><input id="new-per" type="number" name="per_floor_fee" value="{{ old('per_floor_fee', 2) }}" min="0" max="100" step="0.01" required></div>
                <div class="dz-wide"><label class="dz-check"><input type="checkbox" name="is_active" value="1" checked> Customers can choose this building</label></div>
            </div>
            <div class="dz-actions"><button type="submit" class="dz-btn dz-btn--primary">Add building</button></div>
        </form>
    </section>

    <section class="dz-card">
        <h2>Recent changes</h2>
        <p class="dz-sub">Every change to delivery fees and buildings is recorded here: who made it and when.</p>
        @if ($logs->count())
            <div style="overflow-x:auto">
                <table class="dz-table">
                    <thead><tr><th>When</th><th>Who</th><th>What changed</th></tr></thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td style="white-space:nowrap" title="{{ $log->created_at->format('M d, Y h:i A') }}">{{ $log->created_at->diffForHumans() }}</td>
                                <td>{{ $log->user?->name ?? '—' }}</td>
                                <td>{{ $log->summary }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="dz-empty">No changes yet.</p>
        @endif
    </section>
</x-layouts.admin>
