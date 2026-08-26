<section class="rounded-2xl border border-emerald-300 bg-emerald-50/50 p-6 dark:border-emerald-900 dark:bg-emerald-950/20">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
        <div><h2 class="text-lg font-semibold">Surplus CSV import plan</h2><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $surplusCsvImportReview['import']->original_name }} · {{ $surplusCsvImportReview['import']->row_count }} data rows. Structured CSV values were mapped deterministically and were not sent to Gemini.</p></div>
        <span class="self-start rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-900 dark:text-amber-200">Risk level 2 · Approval required</span>
    </div>

    @if($surplusCsvImportReview['duplicate_file'])<div class="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"><strong>Duplicate file warning:</strong> this exact CSV hash was imported previously. Existing parcel cases will still be skipped during execution.</div>@endif
    <div class="mt-5 grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl bg-white p-4 dark:bg-slate-900"><p class="text-xs uppercase tracking-wide text-slate-500">Valid rows</p><p class="mt-2 text-2xl font-semibold">{{ $surplusCsvImportReview['valid_rows'] }}</p></div>
        <div class="rounded-xl bg-white p-4 dark:bg-slate-900"><p class="text-xs uppercase tracking-wide text-slate-500">Invalid rows</p><p class="mt-2 text-2xl font-semibold">{{ $surplusCsvImportReview['invalid_rows'] }}</p></div>
        <div class="rounded-xl bg-white p-4 dark:bg-slate-900"><p class="text-xs uppercase tracking-wide text-slate-500">Existing cases</p><p class="mt-2 text-2xl font-semibold">{{ $surplusCsvImportReview['duplicate_cases'] }}</p></div>
    </div>
</section>

<form method="POST" action="{{ route('vvr-ai.surplus-csv-imports.approve', [$conversation, $surplusCsvImportReview['import']]) }}" class="space-y-6">@csrf
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div><h2 class="text-lg font-semibold">Confirm property jurisdiction</h2><p class="mt-1 text-sm text-slate-500">The CSV State column is the claimant's mailing state. Confirm the state and county where these parcels are located.</p></div>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div><label for="case_state" class="text-sm font-medium">Parcel state</label><input id="case_state" name="case_state" required maxlength="2" value="{{ old('case_state', $surplusCsvImportReview['import']->default_state ?: 'FL') }}" class="form-input uppercase"><x-form-error name="case_state" /></div>
            <div><label for="county" class="text-sm font-medium">Parcel county</label><input id="county" name="county" required maxlength="120" value="{{ old('county', $surplusCsvImportReview['import']->default_county) }}" class="form-input" placeholder="Orange"><x-form-error name="county" /></div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div><h2 class="text-lg font-semibold">Rows proposed for creation or update</h2><p class="mt-1 text-sm text-slate-500">Owners and skip-traced relatives are matched to existing contacts before new contacts are created. Existing parcel cases are updated and receive the new contact links; new parcels receive a Research-stage case, 12% fee calculation, and property-research task.</p></div>
        <x-form-error name="selected_rows" />
        <div class="mt-5 overflow-x-auto"><table class="w-full min-w-[1550px] text-left text-sm"><thead><tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800"><th class="py-3 pr-3">Include</th><th class="py-3 pr-3">CSV row</th><th class="py-3 pr-3">Owner contact</th><th class="py-3 pr-3">Skip-traced relatives</th><th class="py-3 pr-3">Mailing address</th><th class="py-3 pr-3">Parcel</th><th class="py-3 pr-3">Tax deed / certificate</th><th class="py-3 pr-3">Sale date</th><th class="py-3 pr-3">Surplus</th><th class="py-3 pr-3">VVR fee pay</th><th class="py-3">Resolution</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($surplusCsvImportReview['rows'] as $reviewRow)
                    @php($row = $reviewRow['model'])
                    @php($selectable = $row->status === 'ready')
                    <tr @class(['bg-rose-50/60 dark:bg-rose-950/20' => $row->status === 'invalid', 'bg-amber-50/60 dark:bg-amber-950/20' => $reviewRow['surplus_match']])>
                        <td class="py-3 pr-3"><input type="checkbox" name="selected_rows[]" value="{{ $row->id }}" @disabled(!$selectable) @checked($selectable && (!session()->hasOldInput() || in_array($row->id, array_map('intval', old('selected_rows', [])), true))) class="rounded border-slate-300"></td>
                        <td class="py-3 pr-3 font-medium">{{ $row->row_number }}</td>
                        <td class="py-3 pr-3"><span class="font-medium">{{ $row->first_name }} {{ $row->last_name }}</span>@if($reviewRow['contact_match'])<span class="mt-1 block text-xs text-emerald-700 dark:text-emerald-400">Reuse existing #{{ $reviewRow['contact_match']->id }}</span>@else<span class="mt-1 block text-xs text-violet-700 dark:text-violet-300">Create one shared Surplus contact</span>@endif @if($reviewRow['contact_group_count'] > 1)<span class="mt-1 block text-xs font-semibold text-slate-500">{{ $reviewRow['contact_group_count'] }} parcels for this contact</span>@endif</td>
                        <td class="py-3 pr-3">@forelse($row->related_contacts_json ?? [] as $relative)<span class="block font-medium">{{ $relative['first_name'] }} {{ $relative['last_name'] }}</span><span class="mb-1 block text-xs text-slate-500">{{ $relative['possible_type'] ?: 'Relative' }}@if(isset($relative['age'])) · age {{ $relative['age'] }}@endif</span>@empty<span class="text-slate-400">None supplied</span>@endforelse</td>
                        <td class="py-3 pr-3">{{ $row->mailing_address_line_1 }}<span class="block text-xs text-slate-500">{{ $row->mailing_city }}, {{ $row->mailing_state }} {{ $row->mailing_postal_code }} · {{ $row->mailing_country }}</span>@if($reviewRow['mailing_address_conflict'])<span class="mt-1 block text-xs font-semibold text-amber-700 dark:text-amber-300">Different addresses appear for this name. The first selected row supplies the new contact address.</span>@endif</td>
                        <td class="py-3 pr-3 font-mono text-xs">{{ $row->parcel_id }}</td>
                        <td class="py-3 pr-3"><span class="font-medium">{{ $row->tax_deed_number ?: '—' }}</span><span class="block text-xs text-slate-500">Cert: {{ $row->certificate_number ?: '—' }}</span></td>
                        <td class="py-3 pr-3">{{ $row->sale_date?->format('M j, Y') ?: '—' }}</td>
                        <td class="py-3 pr-3 font-medium">{{ $row->surplus_amount !== null ? '$'.number_format((float)$row->surplus_amount, 2) : '—' }}</td>
                        <td class="py-3 pr-3 font-semibold text-emerald-700 dark:text-emerald-400">${{ number_format($reviewRow['projected_fee'], 2) }}<span class="block text-[10px] font-normal uppercase tracking-wide">12%</span></td>
                        <td class="py-3">@if($row->status === 'invalid')<span class="font-semibold text-rose-700 dark:text-rose-300">Invalid</span><ul class="mt-1 list-disc pl-4 text-xs text-rose-700 dark:text-rose-300">@foreach($row->errors_json ?? [] as $error)<li>{{ $error }}</li>@endforeach</ul>@elseif($reviewRow['surplus_match'])<a href="{{ route('surplus.show', $reviewRow['surplus_match']) }}" target="_blank" class="font-semibold text-amber-700 hover:underline dark:text-amber-300">Update and link: {{ $reviewRow['surplus_match']->case_number }}</a>@else<span class="font-semibold text-emerald-700 dark:text-emerald-400">Create case and link</span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </section>

    <section class="rounded-2xl border border-amber-300 bg-amber-50 p-6 dark:border-amber-900 dark:bg-amber-950/30"><h2 class="font-semibold">Approval required</h2><p class="mt-2 text-sm text-slate-600 dark:text-slate-300">All selected rows execute in one database transaction. Permissions and duplicates are checked again immediately before creation. If a required row fails, every write from this approval is rolled back. Mailing addresses will never be copied into property-address fields.</p><div class="mt-5 flex justify-end"><button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-600">Approve selected rows</button></div></section>
</form>
