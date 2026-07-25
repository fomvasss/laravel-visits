@extends('visits::layout')

@section('title', 'Overview')

@section('content')
    @php
        $dimensionLabels = [
            'utm_source' => 'UTM source', 'referrer_host' => 'Referrer', 'country_code' => 'Country',
            'device_type' => 'Device', 'client_type' => 'Client type', 'action' => 'Conversion action',
        ];
    @endphp

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

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach($breakdowns as $dimension => $values)
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-3">{{ $dimensionLabels[$dimension] ?? $dimension }}</div>
                @if($breakdowns[$dimension]->isEmpty())
                    <div class="text-xs text-gray-400 dark:text-gray-600">No data</div>
                @else
                    <div class="relative" style="height: {{ max(60, $breakdowns[$dimension]->count() * 28) }}px">
                        <canvas class="viz-breakdown" data-dimension="{{ $dimension }}"></canvas>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- table view fallback — see dataviz skill accessibility check: a table equivalent must
         exist alongside every chart --}}
    <details class="mt-6 text-xs">
        <summary class="cursor-pointer text-gray-500 dark:text-gray-400">Show as table</summary>
        <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach($breakdowns as $dimension => $values)
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="font-medium text-gray-500 dark:text-gray-400 mb-2">{{ $dimensionLabels[$dimension] ?? $dimension }}</div>
                    @forelse($breakdowns[$dimension] as $value => $count)
                        <div class="flex justify-between py-1 border-b border-gray-100 dark:border-gray-800 last:border-0">
                            <span class="text-gray-700 dark:text-gray-300">{{ $value }}</span>
                            <span class="text-gray-500 dark:text-gray-400">{{ number_format($count) }}</span>
                        </div>
                    @empty
                        <div class="text-gray-400 dark:text-gray-600">No data</div>
                    @endforelse
                </div>
            @endforeach
        </div>
    </details>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
        <script>
            (function () {
                // sequential blue, single hue — every chart here is one metric's magnitude
                // (a trend or a category breakdown), never series identity, so one hue is correct
                // per the dataviz skill's color-formula (no categorical palette needed).
                const isDark = document.documentElement.classList.contains('dark');
                const accent = isDark ? '#3987e5' : '#2a78d6';
                const grid = isDark ? '#2c2c2a' : '#e1e0d9';

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

                const ink = isDark ? '#c3c2b7' : '#52514e';

                const breakdowns = @json($breakdowns);
                document.querySelectorAll('.viz-breakdown').forEach(function (canvas) {
                    const dimension = canvas.dataset.dimension;
                    const entries = Object.entries(breakdowns[dimension] || {});
                    new Chart(canvas, {
                        type: 'bar',
                        data: {
                            labels: entries.map(function (e) { return e[0]; }),
                            datasets: [{
                                data: entries.map(function (e) { return e[1]; }),
                                backgroundColor: accent,
                                borderRadius: 4,
                                maxBarThickness: 18,
                            }],
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: { beginAtZero: true, grid: { color: grid }, ticks: { color: ink, precision: 0 } },
                                // autoSkip false — Chart.js drops middle category labels on a
                                // short canvas like this (~28px/bar) if it judges them too
                                // cramped to fit; with only a handful of categories there's
                                // always room, so force every label to render
                                y: { grid: { display: false }, ticks: { color: ink, autoSkip: false } },
                            },
                            plugins: {
                                legend: { display: false },
                                // xAlign 'left' grows the tooltip box to the right of the
                                // cursor instead of centering on it — otherwise it can render
                                // directly over the y-axis category labels on the left edge
                                tooltip: { xAlign: 'left', yAlign: 'center' },
                            },
                        },
                    });
                });
            })();
        </script>
    @endpush
@endsection
