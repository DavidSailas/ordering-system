<x-layouts.admin title="Order security">
    <style>
        .os-grid { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); gap: 16px; align-items: start; margin-bottom: 20px; }
        .os-card { padding: 20px; border-radius: 16px; background: #fff; box-shadow: 0 1px 3px rgba(20, 24, 40, .08); }
        .os-card h2 { margin: 0 0 4px; font-size: 16px; }
        .os-card p.os-sub { margin: 0 0 14px; color: #6b7385; font-size: 13.5px; }
        .os-status { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .os-pill { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 99px; font-size: 13px; font-weight: 700; }
        .os-pill--on { background: #dcfce7; color: #14532d; }
        .os-pill--off { background: #f3f4f6; color: #4b5563; }
        .os-qr { display: grid; justify-items: center; gap: 12px; padding: 16px; border: 1px solid #eceff3; border-radius: 14px; }
        .os-qr-box { width: min(260px, 100%); aspect-ratio: 1; }
        .os-qr-box svg { display: block; width: 100%; height: 100%; }
        .os-code { text-align: center; }
        .os-code small { display: block; color: #6b7385; font-size: 12px; letter-spacing: .06em; text-transform: uppercase; }
        .os-code strong { font-size: 28px; letter-spacing: .25em; }
        .os-link { display: flex; gap: 8px; align-items: center; width: 100%; }
        .os-link input { flex: 1; min-width: 0; padding: 9px 12px; border: 1px solid #d9dce3; border-radius: 10px; font-size: 13px; background: #f8f9fb; }
        .os-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
        .os-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: 8px 16px; border: 1px solid #e3e6ec; border-radius: 99px; background: #fff; color: inherit; font: inherit; font-size: 13.5px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .os-btn:hover { background: #f4f5f8; }
        .os-btn--primary { border-color: var(--brand, #e25806); background: var(--brand, #e25806); color: #fff; }
        .os-btn--primary:hover { background: var(--brand-600, #c94d05); }
        .os-btn--danger:hover { background: #fee2e2; color: #b91c1c; }
        .os-list { margin: 0; padding: 0; list-style: none; display: grid; gap: 8px; font-size: 13.5px; }
        .os-list li { display: flex; justify-content: space-between; gap: 12px; padding: 9px 12px; border-radius: 10px; background: #f8f9fb; }
        .os-list b { font-weight: 700; }
        .os-form { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr) auto; gap: 8px; align-items: end; margin-bottom: 14px; }
        .os-form label { display: block; margin-bottom: 4px; color: #4b5563; font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .os-form input { width: 100%; padding: 10px 12px; border: 1px solid #d9dce3; border-radius: 10px; background: #fff; font-size: 14px; }
        .os-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        .os-table th { padding: 8px 10px; color: #6b7385; font-size: 12px; font-weight: 600; text-align: left; border-bottom: 1px solid #eceff3; }
        .os-table td { padding: 10px; border-bottom: 1px solid #f1f2f6; vertical-align: middle; }
        .os-empty { padding: 22px; text-align: center; color: #6b7385; font-size: 14px; }
        @media (max-width: 980px) { .os-grid { grid-template-columns: minmax(0, 1fr); } }
        @media (max-width: 640px) { .os-form { grid-template-columns: minmax(0, 1fr); } }
    </style>

    <x-admin.page-title title="Order security" subtitle="Keep prank orders out: the QR code posted at school, the limits in force and the blocked phone numbers." />

    @if ($errors->any())
        <div class="ui-alert ui-alert--bad mb-4" role="alert"><x-icon name="alert" class="ui-i" />{{ $errors->first() }}</div>
    @endif

    <div class="os-grid">
        <section class="os-card">
            <div class="os-status">
                <div>
                    <h2>Campus pass (QR code)</h2>
                    <p class="os-sub" style="margin:0">Only people who scanned the QR code at school can check out. Everyone can still look at the menu.</p>
                </div>
                <span class="os-pill {{ $enabled ? 'os-pill--on' : 'os-pill--off' }}">{{ $enabled ? 'ON' : 'OFF' }}</span>
            </div>

            <div class="os-qr">
                <div class="os-qr-box" id="qr-box" aria-label="QR code for {{ $link }}"></div>
                <div class="os-code"><small>Can't scan? Type this code</small><strong>{{ $code }}</strong></div>
                <div class="os-link">
                    <input id="pass-link" type="text" value="{{ $link }}" readonly aria-label="Pass link">
                    <button type="button" class="os-btn" id="copy-link">Copy link</button>
                </div>
            </div>

            <div class="os-actions">
                <a class="os-btn os-btn--primary" href="{{ route('admin.order-security.poster') }}" target="_blank" rel="noopener">Print poster</a>
                <button type="button" class="os-btn" id="download-qr">Download QR image</button>
                <form method="POST" action="{{ route('admin.order-security.pass') }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="enabled" value="{{ $enabled ? 0 : 1 }}">
                    <button type="submit" class="os-btn {{ $enabled ? 'os-btn--danger' : 'os-btn--primary' }}">{{ $enabled ? 'Turn off' : 'Turn on' }}</button>
                </form>
                <form method="POST" action="{{ route('admin.order-security.regenerate') }}" data-confirm="Create a new QR code? The old posters will stop working and everyone has to scan again." data-confirm-title="Create a new QR code?" data-confirm-ok="Yes, create new">
                    @csrf
                    <button type="submit" class="os-btn os-btn--danger">New QR code</button>
                </form>
            </div>
            <p class="os-sub" style="margin:12px 0 0">Print the poster, post it at the canteen and in classrooms, then press <b>Turn on</b>. A scanned phone can order for {{ $days }} days. If the code leaks, press <b>New QR code</b> and post the new poster.</p>
        </section>

        <section class="os-card">
            <h2>Limits in force</h2>
            <p class="os-sub">These stop one person from flooding the canteen with orders.</p>
            <ul class="os-list">
                <li><span>Unpaid orders one phone number may have open</span><b>{{ $limits['max_open_unpaid'] ?: 'No limit' }}</b></li>
                <li><span>Unpaid orders one connection may have open</span><b>{{ $limits['max_open_unpaid_per_ip'] ?: 'No limit' }}</b></li>
                <li><span>Biggest order that can be paid in cash</span><b>{{ $limits['max_cash_total'] ? config('restaurant.currency').number_format($limits['max_cash_total']) : 'No limit' }}</b></li>
                <li><span>Missed orders before an automatic pause</span><b>{{ $strikeRule['max'] ?: 'Off' }}</b></li>
                <li><span>Delivery details</span><b>Building + floor + room, paid in advance</b></li>
            </ul>
            <p class="os-sub" style="margin:12px 0 0">Bigger orders must be paid by GCash or Maya first. The numbers can be changed in the site settings (ORDER_MAX_OPEN_UNPAID, ORDER_MAX_CASH_TOTAL).</p>
        </section>
    </div>

    <section class="os-card" style="margin-bottom:20px">
        <h2>School ID at checkout</h2>
        <p class="os-sub">Students and teachers can tap or scan their school ID (and type their last name) when they order. Their name is then filled in from the school list, and missed orders follow the ID instead of the phone number. There are <b>{{ number_format($rosterCount) }}</b> people on the school list. <a href="{{ route('admin.school-ids.index') }}">Open the school list →</a></p>
        <form method="POST" action="{{ route('admin.order-security.school-id') }}" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            @csrf @method('PATCH')
            <input type="hidden" name="required" value="{{ $schoolIdRequired ? 0 : 1 }}">
            <b>{{ $schoolIdRequired ? 'A school ID is required to order' : 'A school ID is optional' }}</b>
            <button type="submit" class="os-btn">{{ $schoolIdRequired ? 'Make it optional' : 'Require it' }}</button>
        </form>
        @if ($schoolIdRequired && $rosterCount === 0)
            <p class="os-sub" style="color:#b91c1c;margin-top:10px">The school list is empty, so nobody can order until you add people to it.</p>
        @endif
    </section>

    <section class="os-card" style="margin-bottom:20px">
        <h2>Missed orders &amp; automatic pauses</h2>
        @if ($strikeRule['max'])
            <p class="os-sub">An order that nobody pays for in time is a <b>missed order</b>. After <b>{{ $strikeRule['max'] }}</b> missed orders a phone number is paused for <b>{{ $strikeRule['pause_days'] }} days</b>, then it can order again by itself. Missed orders fade after {{ $strikeRule['window_days'] }} days. {{ $recentStrikes }} missed {{ \Illuminate\Support\Str::plural('order', $recentStrikes) }} so far in that time. Nobody has to do anything; lift a pause early only if it was a mistake.</p>
        @else
            <p class="os-sub">Automatic pauses are switched off (ORDER_STRIKES_MAX is 0).</p>
        @endif

        @if ($pauses->count())
            <div style="overflow-x:auto">
                <table class="os-table">
                    <thead><tr><th>Who</th><th>Why</th><th>Paused until</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($pauses as $pause)
                            <tr>
                                <td><b>{{ $pause->label() }}</b>@if ($pause->school_member_id)<small style="display:block;color:#6b7385">school ID</small>@endif</td>
                                <td>{{ $pause->reason }}</td>
                                <td>{{ $pause->ends_at->format('M j, g:i A') }} <small style="color:#6b7385">({{ $pause->ends_at->diffForHumans() }})</small></td>
                                <td style="text-align:right">
                                    <form method="POST" action="{{ route('admin.order-security.lift', $pause) }}" data-confirm="Let {{ $pause->label() }} order again now?" data-confirm-title="Lift this pause?" data-confirm-ok="Yes, lift it">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="os-btn">Lift pause</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="os-empty">No phone numbers are paused right now.</p>
        @endif
    </section>

    <section class="os-card">
        <h2>Blocked phone numbers</h2>
        <p class="os-sub">A blocked number cannot place orders. Cashiers add numbers with <b>Report fake order</b> on an order, and you can add one here.</p>

        <form method="POST" action="{{ route('admin.order-security.block') }}" class="os-form">
            @csrf
            <div><label for="phone">Phone number</label><input id="phone" name="phone" value="{{ old('phone') }}" placeholder="0917 123 4567" inputmode="tel" required></div>
            <div><label for="reason">Reason (optional)</label><input id="reason" name="reason" value="{{ old('reason') }}" maxlength="250" placeholder="e.g. prank orders"></div>
            <button type="submit" class="os-btn os-btn--primary">Block number</button>
        </form>

        @if ($blocked->count())
            <div style="overflow-x:auto">
                <table class="os-table">
                    <thead><tr><th>Phone</th><th>Reason</th><th>Blocked by</th><th>When</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($blocked as $row)
                            <tr>
                                <td><b>{{ $row->phone }}</b></td>
                                <td>{{ $row->reason ?: '—' }}</td>
                                <td>{{ $row->blocker?->name ?? '—' }}</td>
                                <td>{{ $row->created_at->diffForHumans() }}</td>
                                <td style="text-align:right">
                                    <form method="POST" action="{{ route('admin.order-security.unblock', $row) }}" data-confirm="Let {{ $row->phone }} order again?" data-confirm-title="Unblock this number?" data-confirm-ok="Yes, unblock">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="os-btn">Unblock</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $blocked->links() }}</div>
        @else
            <p class="os-empty">No blocked numbers. That is a good sign.</p>
        @endif
    </section>

    <script src="{{ asset('vendor/qrcode.js') }}"></script>
    <script>
        (function () {
            var link = @json($link);
            var qr = qrcode(0, 'M'); qr.addData(link); qr.make();
            document.getElementById('qr-box').innerHTML = qr.createSvgTag({ scalable: true, margin: 2 });

            document.getElementById('copy-link').addEventListener('click', function (e) {
                var input = document.getElementById('pass-link'); input.select();
                (navigator.clipboard ? navigator.clipboard.writeText(link) : Promise.reject()).catch(function () { document.execCommand('copy'); });
                e.target.textContent = 'Copied!'; setTimeout(function () { e.target.textContent = 'Copy link'; }, 1800);
            });

            // PNG for sharing in chats or printing from a phone
            document.getElementById('download-qr').addEventListener('click', function () {
                var n = qr.getModuleCount(), cell = 24, pad = 48, size = n * cell + pad * 2;
                var c = document.createElement('canvas'); c.width = c.height = size;
                var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, size, size); x.fillStyle = '#000';
                for (var r = 0; r < n; r++) for (var k = 0; k < n; k++) if (qr.isDark(r, k)) x.fillRect(pad + k * cell, pad + r * cell, cell, cell);
                var a = document.createElement('a'); a.download = 'cecilian-bites-qr.png'; a.href = c.toDataURL('image/png'); a.click();
            });
        })();
    </script>
</x-layouts.admin>
