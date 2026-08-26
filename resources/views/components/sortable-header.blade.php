@props(['route', 'column', 'label'])
@php
    $active = request('sort') === $column;
    $currentDirection = $active && request('direction') === 'asc' ? 'asc' : 'desc';
    $nextDirection = $active && $currentDirection === 'asc' ? 'desc' : 'asc';
    $query = request()->except('page');
    $query['sort'] = $column;
    $query['direction'] = $nextDirection;
@endphp
<th {{ $attributes->class('px-5 py-3') }} aria-sort="{{ $active ? ($currentDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
    <a href="{{ route($route, $query) }}" class="inline-flex items-center gap-1.5 whitespace-nowrap hover:text-slate-900 dark:hover:text-white">
        <span>{{ $label }}</span>
        <span aria-hidden="true" @class(['text-[10px]', 'text-amber-600 dark:text-amber-400' => $active, 'text-slate-300 dark:text-slate-600' => ! $active])>{{ $active ? ($currentDirection === 'asc' ? '▲' : '▼') : '↕' }}</span>
        <span class="sr-only">{{ $active ? 'Sorted '.($currentDirection === 'asc' ? 'ascending' : 'descending').'. Activate to sort '.$nextDirection.'.' : 'Activate to sort ascending.' }}</span>
    </a>
</th>
