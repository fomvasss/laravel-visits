@extends('visits::layout')

@section('title', 'Campaigns')

@section('content')
    @php
        $dimensionLabels = [
            'utm_source' => 'UTM source', 'utm_medium' => 'UTM medium', 'utm_campaign' => 'UTM campaign',
            'utm_term' => 'UTM term', 'utm_content' => 'UTM content', 'ref' => 'Ref',
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
        <a href="{{ route('visits.campaigns') }}" class="px-3 py-1.5 rounded border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 text-xs">Reset</a>
    </form>

    @include('visits::dashboard.partials.breakdown-panels', ['breakdowns' => $breakdowns, 'dimensionLabels' => $dimensionLabels])

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
        <script>
            (function () {
                const isDark = document.documentElement.classList.contains('dark');
                const accent = isDark ? '#3987e5' : '#2a78d6';

                @include('visits::dashboard.partials.breakdown-chart-script', ['breakdowns' => $breakdowns])
            })();
        </script>
    @endpush
@endsection
