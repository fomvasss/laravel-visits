@extends('visits::layout')

@section('title', 'Whoami')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <div class="text-sm text-gray-500 dark:text-gray-400">
            What this tracker sees about the current request — nothing here is written to the
            database or set as a cookie.
        </div>
        <a href="{{ route('visits.whoami') }}" class="text-xs text-blue-600 dark:text-blue-400 font-mono">{{ route('visits.whoami') }}</a>
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3 mb-6 text-xs">
        <div>
            <label class="block text-gray-500 dark:text-gray-400 mb-1">Look up a different IP (geo only)</label>
            <input type="text" name="ip" value="{{ $requestedIp }}" placeholder="e.g. 8.8.8.8"
                   class="w-48 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 rounded px-2 py-1 font-mono">
        </div>
        <button class="px-3 py-1.5 rounded bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-900 text-xs">Lookup</button>
        @if($requestedIp)
            <a href="{{ route('visits.me') }}" class="px-3 py-1.5 rounded border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 text-xs">Reset</a>
        @endif
        @if($ipError)
            <span class="text-red-600 dark:text-red-400">{{ $ipError }}</span>
        @endif
    </form>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-3">Request</div>
            <dl class="text-xs space-y-1.5">
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">IP</dt><dd class="font-mono">{{ $data['ip'] }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Visitor token</dt><dd class="font-mono">{{ $data['visitor_token'] ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Referrer</dt><dd>{{ $data['referrer'] ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Locale</dt><dd>{{ $data['locale']['locale'] }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Browser language</dt><dd>{{ $data['locale']['browser_language'] ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">User agent</dt><dd class="truncate max-w-[60%]" title="{{ $data['user_agent'] }}">{{ $data['user_agent'] }}</dd></div>
            </dl>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-3">Device</div>
            <dl class="text-xs space-y-1.5">
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Bot</dt><dd>{{ $data['bot']['is_bot'] ? ($data['bot']['bot_name'] ?? 'yes') : 'no' }}{{ $data['bot']['bot_category'] ? ' (' . $data['bot']['bot_category'] . ')' : '' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Type</dt><dd>{{ $data['device']['device_type'] ?? '—' }}</dd></div>
                @if($data['device']['device_family'] ?? $data['device']['device_model'] ?? null)
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Model</dt><dd>{{ $data['device']['device_family'] ?? '' }} {{ $data['device']['device_model'] ?? '' }}</dd></div>
                @endif
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Platform</dt><dd>{{ $data['device']['platform'] ?? '—' }} {{ $data['device']['platform_version'] }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Browser</dt><dd>{{ $data['device']['browser'] ?? '—' }} {{ $data['device']['browser_version'] }}</dd></div>
                @if($data['device']['browser_engine'] ?? null)
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Engine</dt><dd>{{ $data['device']['browser_engine'] }}</dd></div>
                @endif
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Client type</dt><dd>{{ $data['device']['client_type'] ?? '—' }}</dd></div>
            </dl>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-3">Geo</div>
            <dl class="text-xs space-y-1.5">
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Country</dt><dd>{{ $data['geo']['country_name'] ?? $data['geo']['country_code'] ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Region / city</dt><dd>{{ $data['geo']['region'] ?? '—' }} / {{ $data['geo']['city'] ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Timezone</dt><dd>{{ $data['geo']['timezone'] ?? '—' }}</dd></div>
                @if($data['geo']['lat'] ?? null)
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Coordinates</dt><dd class="font-mono"><a href="https://www.google.com/maps?q={{ $data['geo']['lat'] }},{{ $data['geo']['lng'] }}" target="_blank" rel="noopener" class="text-blue-600 dark:text-blue-400">{{ $data['geo']['lat'] }}, {{ $data['geo']['lng'] }}</a></dd></div>
                @endif
            </dl>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-3">Tracking params on this request</div>
            <dl class="text-xs space-y-1.5">
                @forelse($data['tracking_params']['utm'] + $data['tracking_params']['extra'] as $key => $value)
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">{{ $key }}</dt><dd class="font-mono">{{ $value }}</dd></div>
                @empty
                    <div class="text-gray-400 dark:text-gray-500">none present in the query string</div>
                @endforelse
            </dl>
        </div>
    </div>
@endsection
