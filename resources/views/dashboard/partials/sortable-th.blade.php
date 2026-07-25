@php
    $isActive = $sort === $column;
    $nextDirection = $isActive && $direction === 'asc' ? 'desc' : 'asc';
    $query = array_merge(request()->query(), ['sort' => $column, 'direction' => $nextDirection]);
    unset($query['page']);
    $href = request()->url() . '?' . http_build_query($query);
@endphp
<th class="{{ ($align ?? 'left') === 'right' ? 'text-right' : 'text-left' }} px-3 py-2">
    <a href="{{ $href }}" class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200">
        {{ $label }}
        @if($isActive)
            <span>{{ $direction === 'asc' ? '↑' : '↓' }}</span>
        @endif
    </a>
</th>
