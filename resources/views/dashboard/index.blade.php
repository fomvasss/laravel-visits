@extends('visits::layout')

@section('title', 'Overview')

@section('content')
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
        <button class="px-3 py-1.5 rounded bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-900 text-xs">Apply</button>
    </form>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        @foreach(['visitors' => 'Visitors', 'sessions' => 'Sessions', 'page_views' => 'Page views', 'conversions' => 'Conversions'] as $key => $label)
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($totals[$key]) }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach(['utm_source' => 'UTM source', 'referrer_host' => 'Referrer', 'country_code' => 'Country', 'device_type' => 'Device'] as $dimension => $label)
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-3">{{ $label }}</div>
                @forelse($breakdowns[$dimension] as $value => $count)
                    <div class="flex justify-between text-xs py-1 border-b border-gray-100 dark:border-gray-800 last:border-0">
                        <span class="text-gray-700 dark:text-gray-300">{{ $value }}</span>
                        <span class="text-gray-500 dark:text-gray-400">{{ number_format($count) }}</span>
                    </div>
                @empty
                    <div class="text-xs text-gray-400 dark:text-gray-600">No data</div>
                @endforelse
            </div>
        @endforeach
    </div>
@endsection
