<x-layouts.app title="PreTax Auctions" heading="PreTax Auctions" subheading="Acquire owner interests before scheduled Florida tax deed auctions">
    @php
        $financial = auth()->user()->canViewPreAuctionFinancials();
        $canBulkUpdate = auth()->user()->canManagePreAuctionAcquisitions();
        $pageCaseIds = $cases->pluck('id')->values();
    @endphp

    <section class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950 dark:border-amber-900 dark:bg-amber-950/25 dark:text-amber-100">
        <strong>Separate sister department:</strong> these are owner-interest acquisitions before auction, not Surplus Recovery engagements. The system tracks deed recording, allows the scheduled auction to remain a separate event, and requires a documented entitlement review before any later claim stage.
    </section>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 {{ $financial ? 'xl:grid-cols-5' : 'xl:grid-cols-4' }}">
        @foreach([['Open files',$metrics['open']],['Auctions next 30 days',$metrics['next_30_days']],['Deeds recorded',$metrics['deed_recorded']],['Surplus review',$metrics['surplus_review']]] as [$label,$value])
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-3xl font-bold">{{ number_format($value) }}</p>
            </div>
        @endforeach
        @if($financial)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900 dark:bg-amber-950/20">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Open projected net</p>
                <p class="mt-2 text-3xl font-bold">${{ number_format((float)$metrics['projected_net'],2) }}</p>
            </div>
        @endif
    </div>

    <div class="mb-6 flex flex-col justify-between gap-4 xl:flex-row">
        <form method="GET" class="grid flex-1 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(210px,1fr)_180px_180px_180px_auto]">
            @if(request('sort'))
                <input type="hidden" name="sort" value="{{ request('sort') }}">
                <input type="hidden" name="direction" value="{{ request('direction','asc') }}">
            @endif
            <input name="search" value="{{ request('search') }}" placeholder="Search file, owner, parcel, county" class="form-input mt-0">
            <select name="status" class="form-input mt-0">
                <option value="">All stages</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status')===$status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <select name="assigned_user_id" class="form-input mt-0">
                <option value="">All assigned users</option>
                @foreach($assignees as $user)
                    <option value="{{ $user->id }}" @selected((string)request('assigned_user_id')===(string)$user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
            <select name="auction" class="form-input mt-0">
                <option value="">All auction dates</option>
                <option value="next_30_days" @selected(request('auction')==='next_30_days')>Next 30 days</option>
                <option value="next_90_days" @selected(request('auction')==='next_90_days')>Next 90 days</option>
                <option value="past" @selected(request('auction')==='past')>Past auctions</option>
            </select>
            <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-700">Filter</button>
        </form>
        @can('create',\App\Models\PreAuctionAcquisition::class)
            <a href="{{ route('pre-auction.create') }}" class="rounded-lg bg-amber-400 px-4 py-2 text-center text-sm font-semibold text-slate-950">+ Add acquisition</a>
        @endcan
    </div>

    <section
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        @if($canBulkUpdate) x-data="{ selected: [], bulkStage: @js(old('status', '')), pageIds: @js($pageCaseIds), togglePage() { this.selected = this.allPageSelected() ? [] : [...this.pageIds] }, allPageSelected() { return this.pageIds.length > 0 && this.pageIds.every(id => this.selected.includes(id)) } }" @endif
    >
        @if($canBulkUpdate)
            <form method="POST" action="{{ route('pre-auction.bulk-stage') }}" @submit="if (selected.length === 0 || !confirm(`Move ${selected.length} selected PreTax Auction file${selected.length === 1 ? '' : 's'} to the chosen stage?`)) $event.preventDefault()">
                @csrf
                @method('PATCH')
                <div class="grid gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4 dark:border-slate-800 dark:bg-slate-800/40 lg:grid-cols-[128px_minmax(230px,1fr)_auto_auto] lg:items-center">
                    <p class="min-w-32 text-sm font-semibold"><span x-text="selected.length">0</span> selected</p>
                    <select name="status" x-model="bulkStage" class="form-input mt-0">
                        <option value="">Choose new stage</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" :disabled="selected.length === 0 || !bulkStage" class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-slate-950 disabled:cursor-not-allowed disabled:opacity-50">Change stages</button>
                    <button type="button" x-show="selected.length > 0" @click="selected = []" class="text-sm font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">Clear selection</button>
                </div>
                @error('case_ids')<p class="px-5 pt-4 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
                @error('case_ids.*')<p class="px-5 pt-4 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
                @error('status')<p class="px-5 pt-4 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
        @endif

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60">
                    <tr>
                        @if($canBulkUpdate)
                            <th class="w-12 px-5 py-3">
                                <input type="checkbox" :checked="allPageSelected()" @change="togglePage()" aria-label="Select all PreTax Auction files on this page" class="rounded border-slate-300 text-amber-500 focus:ring-amber-400">
                            </th>
                        @endif
                        <x-sortable-header route="pre-auction.index" column="case" label="File" />
                        <x-sortable-header route="pre-auction.index" column="owner" label="Owner" />
                        <x-sortable-header route="pre-auction.index" column="property" label="Property / parcel" />
                        <x-sortable-header route="pre-auction.index" column="assigned" label="Assigned" />
                        <x-sortable-header route="pre-auction.index" column="auction" label="Auction" />
                        @if($financial)<x-sortable-header route="pre-auction.index" column="economics" label="Projected surplus / net" />@endif
                        <x-sortable-header route="pre-auction.index" column="status" label="Stage" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($cases as $case)
                        <tr @if($canBulkUpdate) :class="selected.includes({{ $case->id }}) ? 'bg-amber-50/60 dark:bg-amber-950/20' : ''" @endif>
                            @if($canBulkUpdate)
                                <td class="px-5 py-4">
                                    <input type="checkbox" name="case_ids[]" value="{{ $case->id }}" x-model.number="selected" aria-label="Select {{ $case->case_number }}" class="rounded border-slate-300 text-amber-500 focus:ring-amber-400">
                                </td>
                            @endif
                            <td class="px-5 py-4">
                                <a href="{{ route('pre-auction.show', $case) }}" class="font-semibold hover:text-amber-600">{{ $case->case_number }}</a>
                                <p class="mt-1 text-xs text-slate-500">{{ $case->tax_deed_number ?: 'No tax deed number' }}</p>
                            </td>
                            <td class="px-5 py-4">
                                @if($case->ownerContact)
                                    <a href="{{ route('contacts.show', $case->ownerContact) }}" class="font-medium hover:text-amber-600">{{ $case->ownerContact->full_name }}</a>
                                    <p class="mt-1 text-xs text-slate-500">{{ $case->ownerContact->company }}</p>
                                @else
                                    <span class="text-slate-500">Unlinked</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                @if($case->property)
                                    <a href="{{ route('properties.show', $case->property) }}" class="font-medium hover:text-amber-600">{{ $case->property->address }}</a>
                                @else
                                    {{ $case->county }} County
                                @endif
                                <p class="mt-1 text-xs text-slate-500">{{ $case->parcel_id }}</p>
                            </td>
                            <td class="px-5 py-4">{{ $case->assignedUser?->name ?: 'Unassigned' }}</td>
                            <td class="px-5 py-4 {{ $case->auction_at?->isPast() && $case->status->isOpen() ? 'font-semibold text-rose-600' : '' }}">{{ $case->auction_at?->format('M j, Y g:i A') ?: '—' }}</td>
                            @if($financial)
                                <td class="px-5 py-4">
                                    {{ $case->projected_surplus !== null ? '$'.number_format((float) $case->projected_surplus, 2) : '—' }}
                                    <p class="mt-1 text-xs text-slate-500">Net: {{ $case->projected_net !== null ? '$'.number_format((float) $case->projected_net, 2) : '—' }}</p>
                                </td>
                            @endif
                            <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold dark:bg-slate-800">{{ $case->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ ($financial ? 7 : 6) + ($canBulkUpdate ? 1 : 0) }}" class="px-6 py-14 text-center text-slate-500">No PreTax Auction acquisitions match the current filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($canBulkUpdate)</form>@endif
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $cases->links() }}</div>
    </section>
</x-layouts.app>
