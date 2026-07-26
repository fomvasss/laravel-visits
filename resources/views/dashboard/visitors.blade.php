@extends('visits::layout')

@section('title', 'Visitors')

@section('content')
    <form method="GET" class="flex flex-wrap items-end gap-3 mb-6 text-xs">
        <div>
            <label class="block text-gray-500 dark:text-gray-400 mb-1">First seen from</label>
            <input type="date" name="from" value="{{ request('from') }}"
                   class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 rounded px-2 py-1">
        </div>
        <div>
            <label class="block text-gray-500 dark:text-gray-400 mb-1">To</label>
            <input type="date" name="to" value="{{ request('to') }}"
                   class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 rounded px-2 py-1">
        </div>
        <div>
            <label class="block text-gray-500 dark:text-gray-400 mb-1">Visitor ID</label>
            <input type="text" name="token" value="{{ request('token') }}"
                   class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 rounded px-2 py-1 font-mono">
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
            <label class="block text-gray-500 dark:text-gray-400 mb-1">UTM source (first-touch)</label>
            <input type="text" name="utm_source" value="{{ request('utm_source') }}"
                   class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 rounded px-2 py-1">
        </div>
        <label class="flex items-center gap-1 pb-1.5">
            <input type="checkbox" name="returning_only" value="1" @checked(request()->boolean('returning_only'))>
            <span class="text-gray-500 dark:text-gray-400">Returning only (2+ sessions)</span>
        </label>
        <label class="flex items-center gap-1 pb-1.5">
            <input type="checkbox" name="with_bots" value="1" @checked(request()->boolean('with_bots'))>
            <span class="text-gray-500 dark:text-gray-400">Include bots</span>
        </label>
        <button class="px-3 py-1.5 rounded bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-900 text-xs">Apply</button>
        <a href="{{ route('visits.visitors') }}" class="px-3 py-1.5 rounded border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 text-xs">Reset</a>
    </form>

    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="text-left px-3 py-2">Visitor ID</th>
                    @include('visits::dashboard.partials.sortable-th', ['column' => 'first_seen_at', 'label' => 'First seen'])
                    @include('visits::dashboard.partials.sortable-th', ['column' => 'last_seen_at', 'label' => 'Last seen'])
                    @include('visits::dashboard.partials.sortable-th', ['column' => 'sessions_count', 'label' => 'Sessions', 'align' => 'right'])
                    @include('visits::dashboard.partials.sortable-th', ['column' => 'country_code', 'label' => 'Country'])
                    @include('visits::dashboard.partials.sortable-th', ['column' => 'device_type', 'label' => 'Device'])
                    @include('visits::dashboard.partials.sortable-th', ['column' => 'utm_source', 'label' => 'UTM source'])
                    <th class="text-left px-3 py-2">User</th>
                    <th class="text-left px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($visitors as $visitor)
                    <tr class="border-t border-gray-100 dark:border-gray-800 {{ $visitor->is_bot ? 'opacity-50' : '' }}">
                        <td class="px-3 py-2 font-mono">{{ Str::limit($visitor->token, 12) }}</td>
                        <td class="px-3 py-2">{{ $visitor->first_seen_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2">{{ $visitor->last_seen_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-3 py-2 text-right">{{ $visitor->sessions_count }}</td>
                        <td class="px-3 py-2">{{ $visitor->country_code }}</td>
                        <td class="px-3 py-2">{{ $visitor->device_type }} / {{ $visitor->browser }}</td>
                        <td class="px-3 py-2">{{ $visitor->utm_source }}</td>
                        <td class="px-3 py-2">{{ $visitor->userDisplayName() ?? ($visitor->user_type ? class_basename($visitor->user_type) . ' #' . $visitor->user_id : '') }}</td>
                        <td class="px-3 py-2">
                            <a href="{{ route('visits.visitor', $visitor->id) }}" class="text-blue-600 dark:text-blue-400">view</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $visitors->links() }}
    </div>
@endsection
