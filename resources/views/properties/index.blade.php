<x-layouts.app title="Properties" heading="Properties" subheading="Land and residential acquisition inventory">
    <div class="mb-6 flex flex-col justify-between gap-4 xl:flex-row xl:items-center">
        <form method="GET" class="grid flex-1 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_150px_170px_100px_auto]">
            @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="direction" value="{{ request('direction', 'asc') }}">@endif
            <input name="search" value="{{ request('search') }}" placeholder="Search parcel, address, city, or county" class="form-input mt-0">
            <select name="status" class="form-input mt-0"><option value="">All statuses</option>@foreach(\App\Enums\PropertyStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select>
            <select name="property_type" class="form-input mt-0"><option value="">All property types</option>@foreach(\App\Enums\PropertyType::cases() as $type)<option value="{{ $type->value }}" @selected(request('property_type') === $type->value)>{{ $type->label() }}</option>@endforeach</select>
            <input name="state" value="{{ request('state') }}" maxlength="2" placeholder="State" class="form-input mt-0 uppercase">
            <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium dark:border-slate-700">Filter</button>
        </form>
        @can('create', \App\Models\Property::class)<a href="{{ route('properties.create') }}" class="rounded-lg bg-amber-400 px-4 py-2 text-center text-sm font-semibold text-slate-950 hover:bg-amber-300">+ Add property</a>@endcan
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto"><table class="w-full min-w-[1250px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60"><tr><x-sortable-header route="properties.index" column="property" label="Property" /><x-sortable-header route="properties.index" column="parcel_county" label="Parcel / county" /><x-sortable-header route="properties.index" column="owner" label="Owner" /><x-sortable-header route="properties.index" column="type" label="Type" /><x-sortable-header route="properties.index" column="acreage" label="Acreage" />@if(auth()->user()->canViewPropertyFinancials())<x-sortable-header route="properties.index" column="all_in_investor" label="All-in / investor" /><x-sortable-header route="properties.index" column="expected_sale_profit" label="Expected sale / profit" />@endif<x-sortable-header route="properties.index" column="status" label="Status" /></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($properties as $property)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-5 py-4"><a href="{{ route('properties.show', $property) }}" class="font-semibold hover:text-amber-600">{{ $property->address }}</a><p class="mt-1 text-xs text-slate-500">{{ $property->city }}, {{ $property->state }} {{ $property->postal_code }}</p></td>
                        <td class="px-5 py-4"><p>{{ $property->parcel_id ?: 'No parcel ID' }}</p><p class="mt-1 text-xs text-slate-500">{{ $property->county }} County</p></td>
                        <td class="px-5 py-4">@if($property->ownerContact)<a href="{{ route('contacts.show', $property->ownerContact) }}" class="hover:text-amber-600">{{ $property->ownerContact->full_name }}</a>@if($property->ownerContact->company)<p class="mt-1 text-xs text-slate-500">{{ $property->ownerContact->company }}</p>@endif @else<span class="text-slate-400">Unassigned</span>@endif</td>
                        <td class="px-5 py-4">{{ $property->property_type->label() }}</td>
                        <td class="px-5 py-4">{{ $property->acreage !== null ? number_format((float) $property->acreage, 4) : '—' }}</td>
                        @can('viewFinancials', $property)<td class="px-5 py-4"><p>{{ $property->all_in_amount !== null ? '$'.number_format((float) $property->all_in_amount, 2) : '—' }}</p><p class="mt-1 text-xs text-slate-500">Investor: {{ $property->investor_price !== null ? '$'.number_format((float) $property->investor_price, 2) : '—' }}</p></td>@endcan
                        @can('viewFinancials', $property)<td class="px-5 py-4"><p>{{ $property->expected_sales_price !== null ? '$'.number_format((float) $property->expected_sales_price, 2) : '—' }}</p><p @class(['mt-1 text-xs', 'text-emerald-600 dark:text-emerald-400' => $property->expected_profit !== null && (float) $property->expected_profit >= 0, 'text-rose-600 dark:text-rose-400' => $property->expected_profit !== null && (float) $property->expected_profit < 0, 'text-slate-500' => $property->expected_profit === null])>Profit: {{ $property->expected_profit !== null ? (((float) $property->expected_profit < 0 ? '−$' : '$').number_format(abs((float) $property->expected_profit), 2)) : '—' }}</p></td>@endcan
                        <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium dark:bg-slate-800">{{ $property->status->label() }}</span></td>
                    </tr>
                @empty <tr><td colspan="{{ auth()->user()->canViewPropertyFinancials() ? 8 : 6 }}" class="px-5 py-14 text-center text-slate-500">No properties match the current filters.</td></tr> @endforelse
            </tbody>
        </table></div>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $properties->links() }}</div>
    </div>
</x-layouts.app>
