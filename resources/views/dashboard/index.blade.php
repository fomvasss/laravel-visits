@extends('visits::layout')

@section('title', 'Overview')

@section('content')
    @php
        $dimensionLabels = [
            'utm_source' => 'UTM source', 'referrer_host' => 'Referrer', 'country_code' => 'Country',
            'device_type' => 'Device', 'client_type' => 'Client type', 'name' => 'Conversion event',
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

    @include('visits::dashboard.partials.breakdown-panels', ['breakdowns' => $breakdowns, 'dimensionLabels' => $dimensionLabels])

    @push('scripts')
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
