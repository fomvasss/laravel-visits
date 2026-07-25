@extends('visits::layout')

@section('title', 'Sessions')

@section('content')
    <form method="GET" class="flex flex-wrap items-end gap-3 mb-6 text-xs">
        <div>
            <label class="block text-gray-500 dark:text-gray-400 mb-1">From</label>
            <input type="date" name="from" value="{{ request('from') }}"
                   class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 rounded px-2 py-1">
        </div>
        <div>
            <label class="block text-gray-500 dark:text-gray-400 mb-1">To</label>
            <input type="date" name="to" value="{{ request('to') }}"
                   class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 rounded px-2 py-1">
        </div>
        <div>
            <label class="block text-gray-500 dark:text-gray-400 mb-1">Country</label>
            <input type="text" name="country_code" value="{{ request('country_code') }}" maxlength="2"
                   class="w-16 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 rounded px-2 py-1">
        </div>
        <div>
            <label class="block text-gray-500 dark:text-gray-400 mb-1">Device</label>
            <input type="text" name="device_type" value="{{ request('device_type') }}"
                   class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 rounded px-2 py-1">
        </div>
        <div>
            <label class="block text-gray-500 dark:text-gray-400 mb-1">UTM source</label>
            <input type="text" name="utm_source" value="{{ request('utm_source') }}"
                   class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 rounded px-2 py-1">
        </div>
        <label class="flex items-center gap-1 pb-1.5">
            <input type="checkbox" name="with_bots" value="1" @checked(request()->boolean('with_bots'))>
            <span class="text-gray-500 dark:text-gray-400">Include bots</span>
        </label>
        <button class="px-3 py-1.5 rounded bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-900 text-xs">Apply</button>
    </form>

    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="text-left px-3 py-2">Started</th>
                    <th class="text-left px-3 py-2">Visitor</th>
                    <th class="text-left px-3 py-2">Country</th>
                    <th class="text-left px-3 py-2">Device</th>
                    <th class="text-left px-3 py-2">UTM source</th>
                    <th class="text-left px-3 py-2">Referrer</th>
                    <th class="text-right px-3 py-2">Page views</th>
                    <th class="text-right px-3 py-2">Duration</th>
                    <th class="text-left px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($sessions as $session)
                    <tr class="border-t border-gray-100 dark:border-gray-800 {{ $session->is_bot ? 'opacity-50' : '' }}">
                        <td class="px-3 py-2">{{ $session->started_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2 font-mono">{{ Str::limit($session->visitor?->token, 12) }}</td>
                        <td class="px-3 py-2">{{ $session->country_code }}</td>
                        <td class="px-3 py-2">{{ $session->device_type }} / {{ $session->browser }}</td>
                        <td class="px-3 py-2">{{ $session->utm_source }}</td>
                        <td class="px-3 py-2">{{ $session->referrer_host }}</td>
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

    <div class="mt-4">
        {{ $sessions->links() }}
    </div>
@endsection
