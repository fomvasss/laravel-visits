@php $dimensionLabels = $dimensionLabels ?? []; @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    @foreach($breakdowns as $dimension => $values)
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-3">{{ $dimensionLabels[$dimension] ?? $dimension }}</div>
            @if($values->isEmpty())
                <div class="text-xs text-gray-400 dark:text-gray-600">No data</div>
            @else
                <div class="relative" style="height: {{ max(60, $values->count() * 28) }}px">
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
                @forelse($values as $value => $count)
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
