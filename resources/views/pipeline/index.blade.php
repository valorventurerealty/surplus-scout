<x-layouts.app title="Pipeline" heading="Property Pipeline" subheading="Move properties through the operating workflow using their status">
    @php
        $stageStyles = [
            'research' => 'border-slate-300 bg-slate-50 dark:border-slate-700 dark:bg-slate-900',
            'bidding' => 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30',
            'owned' => 'border-emerald-300 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30',
            'actively_working' => 'border-sky-300 bg-sky-50 dark:border-sky-800 dark:bg-sky-950/30',
            'marketing' => 'border-violet-300 bg-violet-50 dark:border-violet-800 dark:bg-violet-950/30',
            'under_contract' => 'border-indigo-300 bg-indigo-50 dark:border-indigo-800 dark:bg-indigo-950/30',
            'sold' => 'border-green-300 bg-green-50 dark:border-green-800 dark:bg-green-950/30',
            'archived' => 'border-zinc-300 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900',
        ];
    @endphp

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Properties</p><p class="mt-2 text-3xl font-bold">{{ number_format($totalProperties) }}</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Active pipeline</p><p class="mt-2 text-3xl font-bold">{{ number_format($activeProperties) }}</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Sold</p><p class="mt-2 text-3xl font-bold">{{ number_format($soldProperties) }}</p></div>
        @if($pipelineValue !== null)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Portfolio value</p><p class="mt-2 text-3xl font-bold">${{ number_format($pipelineValue, 2) }}</p></div>
        @endif
    </div>

    <form method="GET" class="mb-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 xl:grid-cols-[minmax(260px,1fr)_120px_190px_auto] dark:border-slate-800 dark:bg-slate-900">
        <input name="search" value="{{ request('search') }}" placeholder="Search parcel, address, city, or county" class="form-input mt-0">
        <input name="state" value="{{ request('state') }}" maxlength="2" placeholder="State" class="form-input mt-0 uppercase">
        <select name="property_type" class="form-input mt-0"><option value="">All property types</option>@foreach(\App\Enums\PropertyType::cases() as $type)<option value="{{ $type->value }}" @selected(request('property_type') === $type->value)>{{ $type->label() }}</option>@endforeach</select>
        <div class="flex gap-2"><button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white dark:bg-amber-400 dark:text-slate-950">Filter</button>@if(request()->hasAny(['search', 'state', 'property_type']))<a href="{{ route('pipeline.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium dark:border-slate-700">Clear</a>@endif</div>
    </form>

    <div class="space-y-4 pb-4" data-pipeline-layout="stacked">
            @foreach($columns as $column)
                <section class="rounded-2xl border p-4 sm:p-5 {{ $stageStyles[$column['status']->value] }}" aria-labelledby="stage-{{ $column['status']->value }}">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 id="stage-{{ $column['status']->value }}" class="text-lg font-semibold">{{ $column['status']->label() }}</h2>
                        <span class="rounded-full bg-white/80 px-2.5 py-1 text-xs font-bold text-slate-600 shadow-sm dark:bg-slate-800 dark:text-slate-200">{{ $column['properties']->count() }}</span>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                        @forelse($column['properties'] as $property)
                            <article class="flex h-full flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                                <a href="{{ route('properties.show', $property) }}" class="font-semibold leading-snug hover:text-amber-600">{{ $property->address ?: 'Address not entered' }}</a>
                                <p class="mt-1 text-xs text-slate-500">{{ implode(', ', array_filter([$property->city, $property->state])) ?: 'Location not entered' }}</p>
                                <dl class="mt-3 flex-1 space-y-1.5 text-xs">
                                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Parcel</dt><dd class="max-w-[170px] truncate text-right font-medium">{{ $property->parcel_id ?: 'Not entered' }}</dd></div>
                                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Owner</dt><dd class="max-w-[170px] truncate text-right font-medium">{{ $property->ownerContact?->full_name ?: 'Unassigned' }}</dd></div>
                                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Type</dt><dd class="text-right font-medium">{{ $property->property_type->label() }}</dd></div>
                                    @can('viewFinancials', $property)<div class="flex justify-between gap-3"><dt class="text-slate-500">Portfolio value</dt><dd class="text-right font-medium">{{ $property->expected_sales_price !== null ? '$'.number_format((float) $property->expected_sales_price, 2) : '—' }}</dd></div>@endcan
                                </dl>
                                @can('update', $property)
                                    <form method="POST" action="{{ route('pipeline.properties.update', $property) }}" class="mt-4 flex gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                                        @csrf @method('PATCH')
                                        <label class="sr-only" for="stage-{{ $property->id }}">Move {{ $property->address }} to stage</label>
                                        <select id="stage-{{ $property->id }}" name="status" class="form-input mt-0 min-w-0 flex-1 text-xs">
                                            @foreach(\App\Enums\PropertyStatus::cases() as $status)<option value="{{ $status->value }}" @selected($property->status === $status)>{{ $status->label() }}</option>@endforeach
                                        </select>
                                        <button class="rounded-lg bg-amber-400 px-3 py-2 text-xs font-semibold text-slate-950 hover:bg-amber-300">Move</button>
                                    </form>
                                @endcan
                            </article>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 bg-white/50 px-4 py-8 text-center text-sm text-slate-500 md:col-span-2 xl:col-span-3 2xl:col-span-4 dark:border-slate-700 dark:bg-slate-900/40">No properties</div>
                        @endforelse
                    </div>
                </section>
            @endforeach
    </div>
</x-layouts.app>
