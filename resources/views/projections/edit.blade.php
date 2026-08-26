@php
    $averageData = collect($categories)->mapWithKeys(fn ($category) => [$category->value => (float) old('assumptions.'.$category->value, $assumptionValues->get($category->value, $category->defaultAverageNetProfit()))]);
    $unitData = [];
    foreach ($categories as $category) {
        foreach ($scenario->years() as $year) {
            foreach (range(1, 12) as $month) {
                $key = "{$category->value}.{$year}.{$month}";
                $unitData[$key] = (int) old("entries.{$category->value}.{$year}.{$month}", $entryValues->get($key, 0));
            }
        }
    }
@endphp
<x-layouts.app title="Edit projections" heading="Edit projection scenario" subheading="{{ $scenario->name }}">
    <form method="POST" action="{{ route('projections.update', $scenario) }}" x-data="{
        averages: {{ Illuminate\Support\Js::from($averageData) }},
        units: {{ Illuminate\Support\Js::from($unitData) }},
        categoryYearUnits(category, year) {
            const prefix = `${category}.${year}.`;
            return Object.entries(this.units).reduce((sum, [key, value]) => key.startsWith(prefix) ? sum + (Number(value) || 0) : sum, 0);
        },
        categoryYearTotal(category, year) {
            return this.categoryYearUnits(category, year) * (Number(this.averages[category]) || 0);
        },
        monthTotal(year, month) {
            return Object.entries(this.units).reduce((sum, [key, value]) => {
                const [category, entryYear, entryMonth] = key.split('.');
                return Number(entryYear) === Number(year) && Number(entryMonth) === Number(month)
                    ? sum + (Number(value) || 0) * (Number(this.averages[category]) || 0)
                    : sum;
            }, 0);
        },
        yearTotal(year) {
            return Object.keys(this.averages).reduce((sum, category) => sum + this.categoryYearTotal(category, year), 0);
        },
        total() {
            return Object.entries(this.units).reduce((sum, [key, value]) => sum + (Number(value) || 0) * (Number(this.averages[key.split('.')[0]]) || 0), 0);
        },
        money(value) {
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value) || 0);
        }
    }" class="space-y-6">
        @csrf @method('PUT')
        @if($errors->any())<div class="rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300"><p class="font-semibold">Review the highlighted projection fields.</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">@include('projections._metadata')</section>
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Projected profit pool</p><p class="mt-2 text-2xl font-bold" x-text="money(total())"></p></article>
            <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900 dark:bg-amber-950/30"><p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">20% VVR</p><p class="mt-2 text-2xl font-bold" x-text="money(total() * .20)"></p></article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">40% Contact 1</p><p class="mt-2 text-2xl font-bold" x-text="money(total() * .40)"></p></article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">40% Contact 2</p><p class="mt-2 text-2xl font-bold" x-text="money(total() * .40)"></p></article>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"><h2 class="font-semibold">Average net-profit assumptions</h2><p class="mt-1 text-sm text-slate-500">Every monthly projection multiplies its unit count by the applicable assumption.</p><div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">@foreach($categories as $category)<div><label for="assumption_{{ $category->value }}" class="text-sm font-medium">{{ $category->label() }}</label><input id="assumption_{{ $category->value }}" name="assumptions[{{ $category->value }}]" type="number" min="0" max="999999999999.99" step="0.01" required x-model.number="averages['{{ $category->value }}']" class="form-input"><p class="mt-1 text-xs text-slate-500">Per {{ strtolower($category->unitLabel()) }}</p><x-form-error name="assumptions.{{ $category->value }}" /></div>@endforeach</div></section>
        @foreach($scenario->years() as $year)
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800"><h2 class="font-semibold">{{ $year }} monthly operating plan</h2></div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-4 py-3">Month</th>
                                @foreach($categories as $category)<th class="px-4 py-3">{{ $category->label() }}<span class="mt-1 block font-normal normal-case">{{ $category->unitLabel() }}</span></th>@endforeach
                                <th class="px-4 py-3 text-right">Projected total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($monthNames as $month => $monthName)
                                <tr>
                                    <th class="px-4 py-3 font-medium">{{ $monthName }}</th>
                                    @foreach($categories as $category)
                                        @php($key = "{$category->value}.{$year}.{$month}")
                                        <td class="px-4 py-3"><input aria-label="{{ $category->label() }} {{ $monthName }} {{ $year }}" name="entries[{{ $category->value }}][{{ $year }}][{{ $month }}]" type="number" min="0" max="1000000" step="1" required x-model.number="units['{{ $key }}']" class="form-input min-w-24"><x-form-error name="entries.{{ $category->value }}.{{ $year }}.{{ $month }}" /></td>
                                    @endforeach
                                    <td class="px-4 py-3 text-right font-semibold" x-text="money(monthTotal({{ $year }}, {{ $month }}))"></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-slate-300 bg-slate-50 font-bold dark:border-slate-700 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-4 py-4">{{ $year }} total</th>
                                @foreach($categories as $category)
                                    <td class="px-4 py-4">
                                        <span x-text="categoryYearUnits('{{ $category->value }}', {{ $year }}).toLocaleString()"></span>
                                        <span class="block text-xs font-medium text-slate-500" x-text="money(categoryYearTotal('{{ $category->value }}', {{ $year }}))"></span>
                                    </td>
                                @endforeach
                                <td class="px-4 py-4 text-right text-base text-amber-700 dark:text-amber-300" x-text="money(yearTotal({{ $year }}))"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        @endforeach
        <div class="sticky bottom-0 flex flex-wrap justify-between gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-slate-800 dark:bg-slate-900/95"><a href="{{ route('projections.index', ['scenario' => $scenario->token]) }}" class="rounded-lg border px-4 py-2 text-sm font-semibold">Cancel</a><button class="rounded-lg bg-amber-400 px-5 py-2 text-sm font-semibold text-slate-950">Save projections</button></div>
    </form>
    @can('delete', $scenario)<form method="POST" action="{{ route('projections.destroy', $scenario) }}" class="mt-6" onsubmit="return confirm('Archive this projection scenario?')">@csrf @method('DELETE')<button class="text-sm font-semibold text-rose-600 hover:underline">Archive scenario</button></form>@endcan
</x-layouts.app>
