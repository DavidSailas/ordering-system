<x-layouts.admin title="Canteen settings">
    <div class="ui-page-head">
        <div>
            <h1>{{ $canteen->name }} · Settings</h1>
            <p>Logo, colour, ordering, delivery locations and payment accounts for this canteen only. Changes apply right away.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="ui-alert ui-alert--bad" role="alert">
            <x-icon name="alert" class="ui-i" />
            <div>@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="ui-form" style="max-width:860px">
        @csrf
        @method('PUT')

        <section class="ui-card ui-card-pad ui-form">
            <p class="ui-section-title" style="margin:0">Ordering</p>
            <label class="ui-switch">
                <input type="checkbox" name="is_open" value="1" @checked(old('is_open', $canteen->is_open ? '1' : '0') == '1')>
                <span><strong>Open for orders</strong><span>Turn off when this canteen is closed. Customers can still see the menu, but cannot check out.</span></span>
            </label>
        </section>

        <section class="ui-card ui-card-pad ui-form">
            <p class="ui-section-title" style="margin:0">Canteen profile</p>

            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                <x-logo :size="72" />
                <div class="ui-field" style="flex:1;min-width:240px">
                    <label for="logo">Logo</label>
                    <input id="logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                    <small>PNG, JPG, WEBP or SVG, up to 2 MB. A square image works best.</small>
                    @if ($canteen->logo_path)
                        <label style="margin-top:8px;display:flex;gap:8px;align-items:center;font-weight:500"><input type="checkbox" name="remove_logo" value="1" style="width:auto"> Remove the uploaded logo (use the default)</label>
                    @endif
                </div>
            </div>

            <div class="ui-form-grid">
                <div class="ui-field">
                    <label for="name">Canteen name</label>
                    <input id="name" name="name" required maxlength="80" value="{{ old('name', $canteen->name) }}">
                </div>
                <div class="ui-field">
                    <label for="accent">Brand colour</label>
                    <input id="accent" type="color" name="accent" value="{{ old('accent', $canteen->accent) }}" style="height:44px;padding:4px">
                    <small>Buttons, highlights and the logo mark use this colour for this canteen.</small>
                </div>
            </div>
            <div class="ui-field">
                <label for="tagline">Short description</label>
                <input id="tagline" name="tagline" maxlength="160" value="{{ old('tagline', $canteen->tagline) }}" placeholder="e.g. Filipino home-style meals">
                <small>Shown on the "Choose a canteen" page.</small>
            </div>
            <div class="ui-form-grid">
                <div class="ui-field">
                    <label for="phone">Contact number</label>
                    <input id="phone" name="phone" maxlength="40" value="{{ old('phone', $canteen->phone) }}">
                </div>
                <div class="ui-field">
                    <label for="address">Address / location</label>
                    <input id="address" name="address" maxlength="255" value="{{ old('address', $canteen->address) }}">
                </div>
            </div>
            <div class="ui-field">
                <label for="receipt_footer">Receipt footer message</label>
                <input id="receipt_footer" name="receipt_footer" maxlength="160" value="{{ old('receipt_footer', $canteen->receipt_footer) }}">
            </div>
        </section>

        <section class="ui-card ui-card-pad ui-form">
            <p class="ui-section-title" style="margin:0">Delivery locations</p>
            <div class="ui-field">
                <label for="buildings">Buildings</label>
                <textarea id="buildings" name="buildings" rows="5" placeholder="Main Building&#10;Science Building&#10;Library">{{ old('buildings', $buildingsText) }}</textarea>
                <small>One building per line. Customers pick one at checkout and type their room number. Leave empty to let them type the building themselves.</small>
            </div>
        </section>

        <section class="ui-card ui-card-pad ui-form">
            <p class="ui-section-title" style="margin:0">Online payment accounts (this canteen's own)</p>
            <p style="margin:-6px 0 0;font-size:13px;color:var(--muted)">Customers send GCash / Maya payments to these accounts. A method with no number is hidden at checkout.</p>
            <div class="ui-form-grid">
                <div class="ui-field"><label for="gcash_number">GCash number</label><input id="gcash_number" name="gcash_number" maxlength="40" value="{{ old('gcash_number', $canteen->gcash_number) }}"></div>
                <div class="ui-field"><label for="gcash_name">GCash account name</label><input id="gcash_name" name="gcash_name" maxlength="80" value="{{ old('gcash_name', $canteen->gcash_name) }}"></div>
                <div class="ui-field"><label for="maya_number">Maya number</label><input id="maya_number" name="maya_number" maxlength="40" value="{{ old('maya_number', $canteen->maya_number) }}"></div>
                <div class="ui-field"><label for="maya_name">Maya account name</label><input id="maya_name" name="maya_name" maxlength="80" value="{{ old('maya_name', $canteen->maya_name) }}"></div>
            </div>
        </section>

        <div><button type="submit" class="ui-btn ui-btn--primary"><x-icon name="check" class="ui-i ui-i--sm" />Save settings</button></div>
    </form>
</x-layouts.admin>
