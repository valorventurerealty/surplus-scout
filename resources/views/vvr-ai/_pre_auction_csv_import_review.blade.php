<section class="rounded-2xl border border-amber-300 bg-amber-50/60 p-6 shadow-sm dark:border-amber-900 dark:bg-amber-950/20">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
        <div>
            <h2 class="text-lg font-semibold">PreTax Auctions CSV import plan</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $preAuctionCsvImportReview['import']->original_name }} · {{ $preAuctionCsvImportReview['import']->row_count }} data row(s). Parsed deterministically on the VVR server; this CSV was not sent to Gemini.</p>
        </div>
        <span class="self-start rounded-full bg-amber-200 px-3 py-1 text-xs font-semibold text-amber-900 dark:bg-amber-900 dark:text-amber-200">Risk level 2 · Approval required</span>
    </div>

    @if($preAuctionCsvImportReview['duplicate_file'])
        <div class="mt-5 rounded-xl border border-amber-300 bg-white p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-slate-900 dark:text-amber-200"><strong>Duplicate file warning:</strong> this exact CSV was completed previously. Existing contacts, cases, and tasks will be reused instead of duplicated.</div>
    @endif

    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-white p-4 dark:bg-slate-900"><p class="text-xs text-slate-500">Valid rows</p><p class="mt-1 text-xl font-semibold">{{ $preAuctionCsvImportReview['valid_rows'] }}</p></div>
        <div class="rounded-xl bg-white p-4 dark:bg-slate-900"><p class="text-xs text-slate-500">Invalid rows</p><p class="mt-1 text-xl font-semibold">{{ $preAuctionCsvImportReview['invalid_rows'] }}</p></div>
        <div class="rounded-xl bg-white p-4 dark:bg-slate-900"><p class="text-xs text-slate-500">Existing cases</p><p class="mt-1 text-xl font-semibold">{{ $preAuctionCsvImportReview['duplicate_cases'] }}</p></div>
        <div class="rounded-xl bg-white p-4 dark:bg-slate-900"><p class="text-xs text-slate-500">Reusable contacts</p><p class="mt-1 text-xl font-semibold">{{ $preAuctionCsvImportReview['duplicate_contacts'] }}</p></div>
    </div>

    <div class="mt-5 rounded-xl border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
        <strong>Controlled behavior</strong>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-slate-600 dark:text-slate-300">
            <li>Contact mailing addresses remain contact data and are never copied into Property Address.</li>
            <li>Assessor Market Value is stored as research context, never as surplus or purchase price.</li>
            <li>Existing Florida county-and-parcel files are updated only where authoritative fields are blank.</li>
            <li>No Property or Calendar record is invented when the CSV lacks a verified property address or auction URL.</li>
            <li>A research task is created once per case for missing auction, title, and property details.</li>
        </ul>
    </div>

    <form method="POST" action="{{ route('vvr-ai.pre-auction-csv-imports.approve', [$conversation, $preAuctionCsvImportReview['import']]) }}" class="mt-6 space-y-5">
        @csrf
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1500px] text-left text-sm">
                <thead><tr class="border-b border-amber-200 text-xs uppercase tracking-wide text-slate-500 dark:border-amber-900"><th class="py-3 pr-3">Include</th><th class="py-3 pr-3">CSV row</th><th class="py-3 pr-3">Contact / owner record</th><th class="py-3 pr-3">Mailing address</th><th class="py-3 pr-3">County / parcel</th><th class="py-3 pr-3">Auction</th><th class="py-3 pr-3">Assessor value</th><th class="py-3 pr-3">Source links</th><th class="py-3">Resolution</th></tr></thead>
                <tbody class="divide-y divide-amber-100 dark:divide-amber-900/60">
                    @foreach($preAuctionCsvImportReview['rows'] as $item)
                        @php($row = $item['model'])
                        <tr class="align-top">
                            <td class="py-4 pr-3"><input type="checkbox" name="selected_rows[]" value="{{ $row->id }}" @checked($row->status === 'ready') @disabled($row->status !== 'ready') class="rounded border-slate-300"></td>
                            <td class="py-4 pr-3 font-medium">{{ $row->row_number }}<span class="block text-xs text-slate-500">{{ $row->listing_type }}</span></td>
                            <td class="py-4 pr-3"><span class="font-medium">{{ trim($row->first_name.' '.$row->last_name) }}</span><span class="block text-xs text-slate-500">Public owner: {{ $row->owner_record_name ?: '—' }}</span></td>
                            <td class="py-4 pr-3">{{ $row->mailing_address_line_1 }}<span class="block text-xs text-slate-500">{{ $row->mailing_city }}, {{ $row->mailing_state }} {{ $row->mailing_postal_code }}</span></td>
                            <td class="py-4 pr-3"><span class="font-medium">{{ str($row->county)->headline() }} County</span><span class="block font-mono text-xs text-slate-500">{{ $row->parcel_id }}</span></td>
                            <td class="py-4 pr-3">{{ $row->auction_at?->format('M j, Y') ?: 'Missing' }}<span class="block text-xs text-amber-700 dark:text-amber-300">Time and auction URL need research</span></td>
                            <td class="py-4 pr-3">{{ $row->assessor_market_value !== null ? '$'.number_format((float) $row->assessor_market_value, 2) : '—' }}</td>
                            <td class="py-4 pr-3"><div class="flex flex-col gap-1">@if($row->appraiser_url)<a href="{{ $row->appraiser_url }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-amber-700 hover:underline dark:text-amber-400">Appraiser ↗</a>@endif @if($row->property_details_url)<a href="{{ $row->property_details_url }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-amber-700 hover:underline dark:text-amber-400">Property details ↗</a>@endif</div></td>
                            <td class="py-4">
                                @if($row->status !== 'ready')
                                    <span class="font-semibold text-rose-700">Invalid</span>
                                    <ul class="mt-1 list-disc pl-4 text-xs text-rose-700">@foreach($row->errors_json ?? [] as $error)<li>{{ $error }}</li>@endforeach</ul>
                                @elseif($item['pre_auction_match'])
                                    <a href="{{ route('pre-auction.show', $item['pre_auction_match']) }}" target="_blank" class="font-semibold text-amber-700 hover:underline dark:text-amber-400">Update {{ $item['pre_auction_match']->case_number }} ↗</a>
                                @else
                                    <span class="font-semibold text-emerald-700">Create PreTax Auction file</span>
                                @endif
                                @if($item['contact_match'])<a href="{{ route('contacts.show', $item['contact_match']) }}" target="_blank" class="mt-1 block text-xs font-semibold text-violet-700 hover:underline dark:text-violet-300">Reuse contact {{ $item['contact_match']->full_name }} ↗</a>@else<span class="mt-1 block text-xs text-slate-500">Create seller contact</span>@endif
                                @foreach($item['conflicts'] as $conflict)<p class="mt-1 text-xs text-rose-700">{{ $conflict }}</p>@endforeach
                                @foreach($row->warnings_json ?? [] as $warning)<p class="mt-1 text-xs text-amber-700 dark:text-amber-300">{{ $warning }}</p>@endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-form-error name="selected_rows" />
        <div class="flex justify-end"><button class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-600">Approve selected rows</button></div>
    </form>
</section>
