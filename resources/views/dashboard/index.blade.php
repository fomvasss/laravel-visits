@extends('visits::layout')

@section('title', 'Overview')

@section('content')
    @php
        $dimensionLabels = [
            'utm_source' => 'UTM source', 'referrer_host' => 'Referrer', 'country_code' => 'Country',
            'device_type' => 'Device', 'client_type' => 'Client type', 'name' => 'Conversion event',
        ];
    @endphp

    <div class="flex items-center gap-1.5 mb-4 text-xs text-gray-500 dark:text-gray-400" title="Sessions active in the last {{ $onlineWindow }} minute(s)">
        <span class="inline-block w-2 h-2 rounded-full {{ $onlineNow > 0 ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-700' }}"></span>
        {{ number_format($onlineNow) }} online now
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3 mb-6 text-xs">
        <div>
            <label class="block text-gray-500 dark:text-gray-400 mb-1">From</label>
            <input type="date" name="from" value="{{ $from->toDateString() }}"
                   class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 rounded px-2 py-1">
        </div>
        <div>
            <label class="block text-gray-500 dark:text-gray-400 mb-1">To</label>
            <input type="date" name="to" value="{{ $to->toDateString() }}"
                   class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 rounded px-2 py-1">
        </div>
        @if($tenants->count() > 1)
            <div>
                <label class="block text-gray-500 dark:text-gray-400 mb-1">Tenant</label>
                <select name="tenant" class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 rounded px-2 py-1">
                    @foreach($tenants as $t)
                        <option value="{{ $t }}" @selected($tenantId === $t)>{{ $t === '' ? '(default)' : $t }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div>
            <label class="block text-gray-500 dark:text-gray-400 mb-1">Breakdown by</label>
            <select name="breakdown_metric" class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 rounded px-2 py-1">
                <option value="sessions" @selected($breakdownMetric === 'sessions')>Sessions (traffic)</option>
                <option value="conversions" @selected($breakdownMetric === 'conversions')>Conversions</option>
            </select>
        </div>
        <button class="px-3 py-1.5 rounded bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-900 text-xs">Apply</button>
        <a href="{{ route('visits.index') }}" class="px-3 py-1.5 rounded border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 text-xs">Reset</a>
    </form>

    @if($botSessions > 0)
        <div class="text-xs text-gray-400 dark:text-gray-600 mb-4">
            Excludes {{ number_format($botSessions) }} bot session(s) ({{ $botPercentage }}% of raw traffic this period) from all charts below —
            <a href="{{ route('visits.sessions', ['with_bots' => 1]) }}" class="text-blue-600 dark:text-blue-400">view bots</a>
        </div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        @foreach(['visitors' => 'Visitors', 'sessions' => 'Sessions', 'page_views' => 'Page views', 'conversions' => 'Conversions'] as $key => $label)
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($totals[$key]) }}</div>
                {{-- fixed-height, position:relative wrapper is required — Chart.js responsive
                     resize measures this container, and without an explicit height here (only
                     on the canvas) container-and-canvas heights chase each other and grow
                     without bound --}}
                <div class="relative mt-2 h-10 w-full">
                    <canvas class="viz-sparkline" data-metric="{{ $key }}" data-label="{{ $label }}"></canvas>
                </div>
            </div>
        @endforeach
    </div>

    @include('visits::dashboard.partials.breakdown-panels', ['breakdowns' => $breakdowns, 'dimensionLabels' => $dimensionLabels])

    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4 mt-6">
        <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-3">Top pages</div>
        @if($topPages->isEmpty())
            <div class="text-xs text-gray-400 dark:text-gray-600">No data</div>
        @else
            <div class="text-xs">
                @foreach($topPages as $page)
                    <div class="flex justify-between gap-4 py-1.5 border-b border-gray-100 dark:border-gray-800 last:border-0">
                        <span class="text-gray-700 dark:text-gray-300 truncate" title="{{ $page['url'] }}">{{ $page['url'] }}</span>
                        <span class="text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ number_format($page['count']) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4 mt-6">
        <div class="flex items-center justify-between mb-3">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Session locations ({{ $mapMarkers->count() }} shown)</div>
            @if($mapMarkers->isNotEmpty())
                <button id="visits-map-fullscreen" type="button"
                        class="text-xs px-3 py-1.5 rounded border border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                    Fullscreen
                </button>
            @endif
        </div>
        @if($mapMarkers->isEmpty())
            <div class="text-xs text-gray-400 dark:text-gray-600">No location data for this period</div>
        @else
            <div id="visits-map" style="height: 550px" class="rounded"></div>

            {{-- table view fallback — see dataviz skill accessibility check --}}
            <details class="mt-3 text-xs">
                <summary class="cursor-pointer text-gray-500 dark:text-gray-400">Show as table</summary>
                <div class="mt-3 max-h-64 overflow-y-auto">
                    @foreach($mapMarkers as $marker)
                        <div class="flex justify-between py-1 border-b border-gray-100 dark:border-gray-800 last:border-0">
                            <span class="text-gray-700 dark:text-gray-300">{{ $marker['city'] ?: '—' }}, {{ $marker['country_code'] ?: '—' }}</span>
                            <span class="text-gray-500 dark:text-gray-400">{{ $marker['started_at'] }}</span>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    </div>

    @push('scripts')
        @if($mapMarkers->isNotEmpty())
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9/dist/leaflet.css">
            <script src="https://unpkg.com/leaflet@1.9/dist/leaflet.js"></script>
            {{-- marker clustering — without it, many sessions at the same (or nearby) coordinates
                 just stack invisibly on top of each other with no indication of how many there
                 are. Clusters collapse nearby points into one badge showing a count, and expand
                 on click/zoom. --}}
            <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5/dist/MarkerCluster.css">
            <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5/dist/MarkerCluster.Default.css">
            <script src="https://unpkg.com/leaflet.markercluster@1.5/dist/leaflet.markercluster.js"></script>
            <script>
                (function () {
                    const accent = document.documentElement.classList.contains('dark') ? '#3987e5' : '#2a78d6';
                    const markers = @json($mapMarkers);

                    // Leaflet.markercluster's 3 built-in size buckets (<10 / <100 / >=100),
                    // recolored from its default yellow/orange/red into one sequential hue
                    // (light -> dark, same accent as the rest of the page) — every visualization
                    // here represents a single metric's magnitude, never series identity, so a
                    // categorical rainbow would be inconsistent (dataviz skill: sequential = one
                    // hue, light to dark).
                    const rgb = [
                        parseInt(accent.slice(1, 3), 16),
                        parseInt(accent.slice(3, 5), 16),
                        parseInt(accent.slice(5, 7), 16),
                    ];

                    // Solid tints (mixed toward white), not alpha transparency — an opacity ramp
                    // reads as "the same blue, faintly" over the map's own light/varied tile
                    // colors (ocean vs land), which is exactly why every cluster looked the same
                    // shade regardless of size. Solid color removes that dependency on what's
                    // underneath and gives an actually readable light -> dark step.
                    function tint(amount) {
                        return 'rgb(' + rgb.map(function (c) {
                            return Math.max(0, Math.min(255, Math.round(c + (255 - c) * amount)));
                        }).join(',') + ')';
                    }

                    // !important: MarkerCluster.Default.css's own rules have the same selector
                    // specificity as these, so without it the winner depends on DOM/load order
                    // rather than intent — this is exactly the legitimate case for !important,
                    // deliberately overriding a third-party plugin's default theme.
                    const style = document.createElement('style');
                    style.textContent = [
                        '.marker-cluster-small { background-color: ' + tint(0.8) + ' !important; }',
                        '.marker-cluster-small div { background-color: ' + tint(0.55) + ' !important; color: #fff !important; }',
                        '.marker-cluster-medium { background-color: ' + tint(0.35) + ' !important; }',
                        '.marker-cluster-medium div { background-color: ' + tint(0.15) + ' !important; color: #fff !important; }',
                        '.marker-cluster-large { background-color: ' + tint(0) + ' !important; }',
                        '.marker-cluster-large div { background-color: ' + tint(-0.25) + ' !important; color: #fff !important; }',
                    ].join('\n');
                    document.head.appendChild(style);

                    const map = L.map('visits-map').setView([20, 0], 2);

                    L.tileLayer(@js(config('visits.dashboard.map_tile_url')), {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                        maxZoom: 18,
                    }).addTo(map);

                    // Leaflet's default bucketing (<10 / <100 / >=100) is a fixed absolute
                    // scale — if the busiest cluster only ever has 15 sessions, "large" never
                    // triggers and everything looks the same; if it has 50,000, "large" covers
                    // every cluster from 100 to 50,000 with no further distinction. Bucketing by
                    // each cluster's share of the total marker count scales with the actual data
                    // instead of a magic number.
                    const totalMarkers = markers.length;
                    const cluster = L.markerClusterGroup({
                        maxClusterRadius: 50,
                        iconCreateFunction: function (c) {
                            const count = c.getChildCount();
                            const ratio = totalMarkers > 0 ? count / totalMarkers : 0;
                            const bucket = ratio < 0.05 ? 'small' : (ratio < 0.2 ? 'medium' : 'large');

                            return new L.DivIcon({
                                html: '<div><span>' + count + '</span></div>',
                                className: 'marker-cluster marker-cluster-' + bucket,
                                iconSize: new L.Point(40, 40),
                            });
                        },
                    });

                    markers.forEach(function (m) {
                        cluster.addLayer(
                            L.circleMarker([m.lat, m.lng], {
                                radius: 5,
                                weight: 1,
                                color: accent,
                                fillColor: accent,
                                fillOpacity: 0.6,
                            }).bindPopup((m.city || 'Unknown') + ', ' + (m.country_code || '?') + '<br>' + m.started_at)
                        );
                    });

                    map.addLayer(cluster);

                    // Fullscreen API on the map div itself (not a Leaflet plugin — just the
                    // native browser API). A plain element doesn't fill the screen on its own
                    // when fullscreened without explicit CSS, and Leaflet needs invalidateSize()
                    // after any manual resize of its container or its tile grid stays misaligned
                    // until the next pan/zoom.
                    const mapEl = document.getElementById('visits-map');
                    const fullscreenStyle = document.createElement('style');
                    fullscreenStyle.textContent = '#visits-map:fullscreen { width: 100vw; height: 100vh; }';
                    document.head.appendChild(fullscreenStyle);

                    document.getElementById('visits-map-fullscreen').addEventListener('click', function () {
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
        @endif
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
        <script>
            (function () {
                // sequential blue, single hue — every chart here is one metric's magnitude
                // (a trend or a category breakdown), never series identity, so one hue is correct
                // per the dataviz skill's color-formula (no categorical palette needed).
                const isDark = document.documentElement.classList.contains('dark');
                const accent = isDark ? '#3987e5' : '#2a78d6';

                const trends = @json($trends);
                const trendDates = @json($trendDates);
                document.querySelectorAll('.viz-sparkline').forEach(function (canvas) {
                    const metric = canvas.dataset.metric;
                    new Chart(canvas, {
                        type: 'line',
                        data: {
                            labels: trendDates,
                            datasets: [{
                                label: canvas.dataset.label,
                                data: trends[metric],
                                borderColor: accent,
                                borderWidth: 2,
                                pointRadius: 0,
                                tension: 0.25,
                                fill: true,
                                backgroundColor: isDark ? 'rgba(57,135,229,0.10)' : 'rgba(42,120,214,0.10)',
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: { x: { display: false }, y: { display: false } },
                            plugins: {
                                legend: { display: false },
                                tooltip: { intersect: false, mode: 'index' },
                            },
                        },
                    });
                });

                @include('visits::dashboard.partials.breakdown-chart-script', ['breakdowns' => $breakdowns])
            })();
        </script>
    @endpush
@endsection
