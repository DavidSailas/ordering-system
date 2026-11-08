<x-layouts.app title="Incoming orders">
    <div class="board-bar">
        <form method="GET" action="{{ route('dashboard') }}" class="board-search" role="search">
            <input type="search" name="q" value="{{ $search }}" class="ui-input" placeholder="Search by order #, name, room or building" aria-label="Search orders">
            <button type="submit" class="ui-btn ui-btn--primary"><x-icon name="search" class="ui-i ui-i--sm" />Search</button>
            @if ($search !== '')
                <a href="{{ route('dashboard') }}" class="ui-btn">Clear</a>
            @endif
        </form>

        <div class="board-chips">
            <span class="board-live" title="This board refreshes by itself">Live · updates every 20s</span>
        </div>
    </div>

    <div id="new-order-toast" class="ui-alert ui-alert--warn" role="status" hidden>
        <x-icon name="bell" class="ui-i" /> <span>A new order just came in.</span>
    </div>

    <div id="board-live">
        <div id="board" data-newest="{{ $newestId }}">
            <div class="board-chips" style="margin:0 0 14px">
                <span class="ui-badge ui-badge--warn">{{ $preparing->count() }} preparing</span>
                <span class="ui-badge ui-badge--info">{{ $ready->count() }} ready</span>
                <span class="ui-badge {{ $unpaidCount ? 'ui-badge--brand' : '' }}">{{ $unpaidCount }} unpaid</span>
            </div>

            <div class="board">
                <section class="board-col" aria-label="Preparing">
                    <div class="board-col-head"><x-icon name="chef" class="ui-i" />Preparing<span class="ui-count">{{ $preparing->count() }}</span></div>
                    <div class="board-col-body">
                        @forelse ($preparing as $order)
                            @include('Cashier.partials.order-card', ['order' => $order])
                        @empty
                            <p class="board-empty">No orders being prepared.</p>
                        @endforelse
                    </div>
                </section>

                <section class="board-col" aria-label="Ready">
                    <div class="board-col-head"><x-icon name="bell" class="ui-i" />Ready for pickup / delivery<span class="ui-count">{{ $ready->count() }}</span></div>
                    <div class="board-col-body">
                        @forelse ($ready as $order)
                            @include('Cashier.partials.order-card', ['order' => $order])
                        @empty
                            <p class="board-empty">Nothing is waiting to be handed over.</p>
                        @endforelse
                    </div>
                </section>

                <section class="board-col" aria-label="Done today">
                    <div class="board-col-head"><x-icon name="check-circle" class="ui-i" />Done today<span class="ui-count">{{ $finished->count() }}</span></div>
                    <div class="board-col-body">
                        @forelse ($finished as $order)
                            @include('Cashier.partials.order-card', ['order' => $order])
                        @empty
                            <p class="board-empty">Completed and canceled orders from today show up here.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var wrap = document.getElementById('board-live');
            var toast = document.getElementById('new-order-toast');
            var baseTitle = document.title;
            var busy = false;

            function beep() {
                try {
                    var ctx = new (window.AudioContext || window.webkitAudioContext)();
                    var osc = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.connect(gain); gain.connect(ctx.destination);
                    osc.frequency.value = 880; gain.gain.value = 0.08;
                    osc.start(); osc.stop(ctx.currentTime + 0.18);
                } catch (e) { /* browsers may block sound until the page was clicked */ }
            }

            function refresh() {
                if (busy || document.hidden) return;
                busy = true;

                fetch(location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.text() : Promise.reject(); })
                    .then(function (html) {
                        var next = new DOMParser().parseFromString(html, 'text/html').getElementById('board');
                        var current = document.getElementById('board');
                        if (!next || !current) return;

                        var newer = parseInt(next.dataset.newest || '0', 10) > parseInt(current.dataset.newest || '0', 10);
                        wrap.replaceChild(next, current);

                        if (newer) {
                            toast.hidden = false;
                            document.title = '(!) New order · ' + baseTitle;
                            beep();
                            setTimeout(function () { toast.hidden = true; document.title = baseTitle; }, 8000);
                        }
                    })
                    .catch(function () { /* offline or session expired: try again next time */ })
                    .then(function () { busy = false; });
            }

            setInterval(refresh, 20000);
            document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
        })();
    </script>
</x-layouts.app>
