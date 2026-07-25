@extends('visits::layout')

@section('title', 'Visitor #' . $visitor->id)

@section('content')
    <a href="{{ route('visits.sessions') }}" class="text-xs text-blue-600 dark:text-blue-400">&larr; back to sessions</a>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 my-6">
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-3">Visitor</div>
            <dl class="text-xs space-y-1.5">
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Token</dt><dd class="font-mono">{{ $visitor->token }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">First seen</dt><dd>{{ $visitor->first_seen_at }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Last seen</dt><dd>{{ $visitor->last_seen_at }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Sessions</dt><dd><a href="{{ route('visits.sessions', ['visitor_id' => $visitor->id, 'with_bots' => $visitor->is_bot ? 1 : null]) }}" class="text-blue-600 dark:text-blue-400">{{ $visitor->sessions->count() }}</a></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Total page views</dt><dd>{{ $visitor->sessions->sum('page_views_count') }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Country / city (last known)</dt><dd>{{ $visitor->geo_meta['country_name'] ?? $visitor->country_code }} / {{ $visitor->city }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Device (last known)</dt><dd>{{ $visitor->device_type }} · {{ $visitor->platform }} · {{ $visitor->browser }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Bot</dt><dd>{{ $visitor->is_bot ? 'yes' : 'no' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">User</dt><dd>{{ $visitor->userDisplayName() ?? ($visitor->user_type ? class_basename($visitor->user_type) . ' #' . $visitor->user_id : 'anonymous') }}</dd></div>
            </dl>
        </div>

        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-3">First-touch attribution</div>
            <dl class="text-xs space-y-1.5">
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Landing URL</dt><dd class="truncate max-w-[60%]" title="{{ $visitor->first_landing_url }}">{{ $visitor->first_landing_url }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Referrer</dt><dd>{{ $visitor->first_referrer_host ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">UTM source/medium</dt><dd>{{ $visitor->utm_source }} / {{ $visitor->utm_medium }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">UTM campaign</dt><dd>{{ $visitor->utm_campaign }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">ref</dt><dd>{{ $visitor->ref }}</dd></div>
                @if($visitor->extra_params)
                    <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Extra params</dt><dd class="font-mono">{{ json_encode($visitor->extra_params) }}</dd></div>
                @endif
            </dl>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="text-left px-3 py-2">Started</th>
                    <th class="text-left px-3 py-2">UTM source</th>
                    <th class="text-left px-3 py-2">Referrer</th>
                    <th class="text-left px-3 py-2">Device</th>
                    <th class="text-right px-3 py-2">Page views</th>
                    <th class="text-right px-3 py-2">Duration</th>
                    <th class="text-left px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($visitor->sessions as $session)
                    <tr class="border-t border-gray-100 dark:border-gray-800 {{ $session->is_bot ? 'opacity-50' : '' }}">
                        <td class="px-3 py-2">{{ $session->started_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2">{{ $session->utm_source }}</td>
                        <td class="px-3 py-2">{{ $session->referrer_host }}</td>
                        <td class="px-3 py-2">{{ $session->device_type }} / {{ $session->browser }}</td>
                        <td class="px-3 py-2 text-right">{{ $session->page_views_count }}</td>
                        <td class="px-3 py-2 text-right">{{ $session->duration_seconds ? gmdate('i:s', $session->duration_seconds) : '—' }}</td>
                        <td class="px-3 py-2">
                            <a href="{{ route('visits.show', $session->id) }}" class="text-blue-600 dark:text-blue-400">view</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
