@extends('visits::layout')

@section('title', 'Live')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">
            Recent activity as it's processed — each pulse fades after a couple of seconds.
            Not instant: events go through a queue before landing here, so this reflects
            "recently processed", not the exact moment it happened.
        </div>
        <span id="visits-live-status" class="text-xs text-gray-400 dark:text-gray-600 whitespace-nowrap ml-4"></span>
    </div>

    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
        <div class="flex items-center justify-between mb-3">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Live events (fading pulses, ~3s)</div>
            <button id="visits-live-map-fullscreen" type="button"
                    class="text-xs px-3 py-1.5 rounded border border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                Fullscreen
            </button>
        </div>
        <div id="visits-live-map" style="height: 650px" class="rounded"></div>
    </div>

    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg mt-4 overflow-hidden">
        <table class="w-full text-xs">
            <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="text-left px-3 py-2">Time</th>
                    <th class="text-left px-3 py-2">Type</th>
                    <th class="text-left px-3 py-2">Name</th>
                    <th class="text-left px-3 py-2">City</th>
                    <th class="text-left px-3 py-2">Country</th>
                </tr>
            </thead>
            <tbody id="visits-live-log"></tbody>
        </table>
    </div>

    @push('scripts')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9/dist/leaflet.css">
        <script src="https://unpkg.com/leaflet@1.9/dist/leaflet.js"></script>
        <script>
            (function () {
                const accent = document.documentElement.classList.contains('dark') ? '#3987e5' : '#2a78d6';
                const transport = @js(config('visits.live.transport', 'poll'));
                const feedUrl = @js(route('visits.live.feed'));
                const sessionUrlTemplate = @js(route('visits.show', ['id' => '__ID__']));
                const streamUrl = @js(route('visits.live.stream'));
                const pollIntervalMs = @js((int) config('visits.live.poll_interval_ms', 10000));
                const statusEl = document.getElementById('visits-live-status');
                const logEl = document.getElementById('visits-live-log');
                const logLimit = 25;

                function addLogRow(e) {
                    const row = document.createElement('tr');
                    row.className = 'border-t border-gray-100 dark:border-gray-800';

                    const cells = [
                        e.created_at ? new Date(e.created_at).toLocaleTimeString() : '',
                        e.type || '',
                        e.name || '',
                        e.city || '',
                        e.country_code || '',
                    ];

                    cells.forEach(function (value, index) {
                        const td = document.createElement('td');
                        td.className = 'px-3 py-2';

                        if (index === 0 && e.session_id) {
                            const link = document.createElement('a');
                            link.href = sessionUrlTemplate.replace('__ID__', e.session_id);
                            link.target = '_blank';
                            link.className = 'text-blue-600 dark:text-blue-400 hover:underline';
                            link.textContent = value;
                            td.appendChild(link);
                        } else {
                            td.textContent = value;
                        }

                        row.appendChild(td);
                    });

                    logEl.insertBefore(row, logEl.firstChild);

                    while (logEl.children.length > logLimit) {
                        logEl.removeChild(logEl.lastChild);
                    }
                }

                const map = L.map('visits-live-map').setView([20, 0], 2);

                L.tileLayer(@js(config('visits.dashboard.map_tile_url')), {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 18,
                }).addTo(map);

                let since = new Date().toISOString();

                // ephemeral by design — no clustering, no persistence. Each pulse grows and
                // fades over ~3s via Leaflet's own setRadius()/setStyle() (animating a CSS
                // radius on an SVG <path> — which is what Leaflet's circleMarker renders as —
                // doesn't work the way it would on a real <circle>, so this drives it frame by
                // frame instead), then removes itself.
                function addPulse(e) {
                    const marker = L.circleMarker([e.lat, e.lng], {
                        radius: 4,
                        color: accent,
                        fillColor: accent,
                        fillOpacity: 0.7,
                        weight: 2,
                        opacity: 0.9,
                    }).bindTooltip((e.name || e.type) + (e.city ? ' — ' + e.city : ''), { direction: 'top' })
                        .addTo(map);

                    const start = performance.now();
                    const duration = 3000;

                    function step(now) {
                        const t = Math.min(1, (now - start) / duration);
                        marker.setRadius(4 + t * 16);
                        marker.setStyle({ opacity: 0.9 * (1 - t), fillOpacity: 0.7 * (1 - t) });

                        if (t < 1) {
                            requestAnimationFrame(step);
                        } else {
                            map.removeLayer(marker);
                        }
                    }

                    requestAnimationFrame(step);
                }

                function handleBatch(data) {
                    since = data.since;
                    // oldest-of-the-batch first, so pulses appear in the order they happened
                    data.events.slice().reverse().forEach(function (e) {
                        addPulse(e);
                        addLogRow(e);
                    });
                    statusEl.textContent = 'updated ' + new Date().toLocaleTimeString();
                }

                if (transport === 'sse') {
                    // EventSource auto-reconnects on its own after any close (including the
                    // server's own sse_max_duration cutoff), but always to the SAME URL it was
                    // constructed with — reusing that would replay everything since the
                    // page-load `since` on every reconnect. Closing it ourselves and opening a
                    // fresh one with the current `since` avoids re-showing old pulses.
                    let source;

                    function connect() {
                        source = new EventSource(streamUrl + '?since=' + encodeURIComponent(since));

                        source.onmessage = function (event) {
                            handleBatch(JSON.parse(event.data));
                        };

                        source.onerror = function () {
                            source.close();
                            statusEl.textContent = 'reconnecting…';
                            setTimeout(connect, 2000);
                        };
                    }

                    connect();
                } else {
                    function poll() {
                        fetch(feedUrl + '?since=' + encodeURIComponent(since))
                            .then(function (r) { return r.json(); })
                            .then(handleBatch)
                            .catch(function () {
                                // fire-and-forget by design — a failed poll must never break the page
                            });
                    }

                    poll();
                    setInterval(poll, pollIntervalMs);
                }

                // Fullscreen API on the map div itself — same approach as the Overview page's
                // session map. Leaflet needs invalidateSize() after any manual resize of its
                // container or its tile grid stays misaligned until the next pan/zoom.
                const mapEl = document.getElementById('visits-live-map');
                const fullscreenStyle = document.createElement('style');
                fullscreenStyle.textContent = '#visits-live-map:fullscreen { width: 100vw; height: 100vh; }';
                document.head.appendChild(fullscreenStyle);

                document.getElementById('visits-live-map-fullscreen').addEventListener('click', function () {
                    if (document.fullscreenElement) {
                        document.exitFullscreen();
                    } else {
                        mapEl.requestFullscreen();
                    }
                });

                document.addEventListener('fullscreenchange', function () {
                    setTimeout(function () { map.invalidateSize(); }, 0);
                });
            })();
        </script>
    @endpush
@endsection
