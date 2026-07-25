@extends('visits::layout')

@section('title', 'Session #' . $session->id)

@section('content')
    <a href="{{ route('visits.sessions') }}" class="text-xs text-blue-600 dark:text-blue-400">&larr; back to sessions</a>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 my-6">
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-3">Session</div>
            <dl class="text-xs space-y-1.5">
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Started</dt><dd>{{ $session->started_at }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Ended</dt><dd>{{ $session->ended_at ?? 'open' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Duration</dt><dd>{{ $session->duration_seconds ? gmdate('H:i:s', $session->duration_seconds) : '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Page views</dt><dd>{{ $session->page_views_count }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">IP</dt><dd>{{ $session->ip }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Country / city</dt><dd>{{ $session->geo_meta['country_name'] ?? $session->country_code }} / {{ $session->city }}</dd></div>
                @if($session->geo_meta['currency_code'] ?? null)
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Currency</dt><dd>{{ $session->geo_meta['currency_code'] }}</dd></div>
                @endif
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Device</dt><dd>{{ $session->device_type }} · {{ $session->platform }}{{ ($v = $session->device_meta['platform_version'] ?? null) ? " $v" : '' }} · {{ $session->browser }}{{ ($v = $session->device_meta['browser_version'] ?? null) ? " $v" : '' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Client type</dt><dd>{{ $session->client_type }}</dd></div>
                @if($session->device_meta['device_family'] ?? $session->device_meta['device_model'] ?? null)
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Model</dt><dd>{{ $session->device_meta['device_family'] ?? '' }} {{ $session->device_meta['device_model'] ?? '' }}</dd></div>
                @endif
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Locale</dt><dd>{{ $session->locale }} ({{ $session->browser_language }})</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Bot</dt><dd>{{ $session->is_bot ? ($session->events->first()?->bot_name ?? 'yes') : 'no' }}</dd></div>
            </dl>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-3">Attribution</div>
            <dl class="text-xs space-y-1.5">
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Landing URL</dt><dd class="truncate max-w-[60%]" title="{{ $session->landing_url }}">{{ $session->landing_url }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Referrer</dt><dd>{{ $session->referrer_host ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">UTM source/medium</dt><dd>{{ $session->utm_source }} / {{ $session->utm_medium }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">UTM campaign</dt><dd>{{ $session->utm_campaign }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">ref</dt><dd>{{ $session->ref }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Visitor token</dt><dd class="font-mono">{{ $session->visitor?->token }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Visitor first seen</dt><dd>{{ $session->visitor?->first_seen_at }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">User</dt><dd>{{ $session->user_type ? class_basename($session->user_type) . ' #' . $session->user_id : 'anonymous' }}</dd></div>
            </dl>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="text-left px-3 py-2">Time</th>
                    <th class="text-left px-3 py-2">Type</th>
                    <th class="text-left px-3 py-2">Action</th>
                    <th class="text-left px-3 py-2">URL</th>
                    <th class="text-left px-3 py-2">Eventable</th>
                </tr>
            </thead>
            <tbody>
                @foreach($session->events as $event)
                    <tr class="border-t border-gray-100 dark:border-gray-800">
                        <td class="px-3 py-2">{{ $event->created_at?->format('H:i:s') }}</td>
                        <td class="px-3 py-2">{{ $event->type }}</td>
                        <td class="px-3 py-2">{{ $event->action }}</td>
                        <td class="px-3 py-2 truncate max-w-xs" title="{{ $event->url }}">{{ $event->url }}</td>
                        <td class="px-3 py-2">{{ $event->eventable_type ? class_basename($event->eventable_type) . ' #' . $event->eventable_id : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
