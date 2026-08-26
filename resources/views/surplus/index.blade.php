<x-layouts.app title="Surplus" heading="Surplus" subheading="Recovery cases, claimant outreach, deadlines, and payments">
    @php
        $financial = auth()->user()->canViewSurplusFinancials();
        $canBulkUpdate = auth()->user()->canManageSurplusCases();
        $pageCaseIds = $cases->pluck('id')->values();
    @endphp

    <div class="mb-6 grid gap-4 sm:grid-cols-2 {{ $financial ? 'xl:grid-cols-5' : 'xl:grid-cols-4' }}">
        @foreach([['Open cases',$metrics['open']],['Ready to submit',$metrics['submit_claim']],['Approved',$metrics['approved']],['Paid',$metrics['paid']]] as [$label,$value])
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-3xl font-bold">{{ number_format($value) }}</p>
            </div>
        @endforeach
        @if($financial)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900 dark:bg-amber-950/20">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Open expected fees</p>
                <p class="mt-2 text-3xl font-bold">${{ number_format((float)$metrics['expected_fees'],2) }}</p>
            </div>
        @endif
    </div>

    <div class="mb-6 flex flex-col justify-between gap-4 xl:flex-row">
        <form method="GET" class="grid flex-1 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(210px,1fr)_170px_180px_170px_auto]">
            @if(request('sort'))
                <input type="hidden" name="sort" value="{{ request('sort') }}">
                <input type="hidden" name="direction" value="{{ request('direction','asc') }}">
            @endif
            <input name="search" value="{{ request('search') }}" placeholder="Search case, claimant, parcel, county" class="form-input mt-0">
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
            <select name="deadline" class="form-input mt-0">
                <option value="">All deadlines</option>
                <option value="overdue" @selected(request('deadline')==='overdue')>Overdue</option>
                <option value="next_30_days" @selected(request('deadline')==='next_30_days')>Next 30 days</option>
                <option value="no_deadline" @selected(request('deadline')==='no_deadline')>No deadline</option>
            </select>
            <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-700">Filter</button>
        </form>
        @can('create',\App\Models\SurplusCase::class)
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('surplus-scout.osceola.index') }}" class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2 text-center text-sm font-semibold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">Osceola Research</a>
                <a href="{{ route('surplus.create') }}" class="rounded-lg bg-amber-400 px-4 py-2 text-center text-sm font-semibold text-slate-950">+ Add surplus case</a>
            </div>
        @endcan
    </div>

    <section
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        @if($canBulkUpdate) x-data="{ selected: [], bulkStage: @js(old('status', '')), bulkCounty: @js(old('county', '')), pageIds: @js($pageCaseIds), togglePage() { this.selected = this.allPageSelected() ? [] : [...this.pageIds] }, allPageSelected() { return this.pageIds.length > 0 && this.pageIds.every(id => this.selected.includes(id)) } }" @endif
    >
        @if($canBulkUpdate)
            <form method="POST" action="{{ route('surplus.bulk-stage') }}" @submit="if (selected.length === 0 || !confirm($event.submitter?.value === 'county' ? `Assign ${selected.length} selected Surplus case${selected.length === 1 ? '' : 's'} to this county?` : `Move ${selected.length} selected Surplus case${selected.length === 1 ? '' : 's'} to the chosen stage?`)) $event.preventDefault()">
                @csrf
                @method('PATCH')
                <div class="grid gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4 dark:border-slate-800 dark:bg-slate-800/40 lg:grid-cols-[128px_minmax(230px,1fr)_auto_minmax(230px,1fr)_auto_auto] lg:items-center">
                    <p class="min-w-32 text-sm font-semibold"><span x-text="selected.length">0</span> selected</p>
                    <select name="status" x-model="bulkStage" class="form-input mt-0">
                        <option value="">Choose new stage</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" name="operation" value="stage" :disabled="selected.length === 0 || !bulkStage" class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-slate-950 disabled:cursor-not-allowed disabled:opacity-50">Change stages</button>
                    <input name="county" x-model="bulkCounty" list="surplus-county-options" placeholder="Enter or choose county" maxlength="120" class="form-input mt-0">
                    <datalist id="surplus-county-options">@foreach($counties as $county)<option value="{{ $county }}">@endforeach</datalist>
                    <button type="submit" name="operation" value="county" :disabled="selected.length === 0 || !bulkCounty.trim()" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 dark:bg-slate-100 dark:text-slate-950">Change counties</button>
                    <button type="button" x-show="selected.length > 0" @click="selected = []" class="text-sm font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">Clear selection</button>
                </div>
                @error('operation')<p class="px-5 pt-4 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
                @error('case_ids')<p class="px-5 pt-4 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
                @error('case_ids.*')<p class="px-5 pt-4 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
                @error('status')<p class="px-5 pt-4 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
                @error('county')<p class="px-5 pt-4 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
        @endif

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1050px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60">
                    <tr>
                        @if($canBulkUpdate)
                            <th class="w-12 px-5 py-3">
                                <input type="checkbox" :checked="allPageSelected()" @change="togglePage()" aria-label="Select all cases on this page" class="rounded border-slate-300 text-amber-500 focus:ring-amber-400">
                            </th>
                        @endif
                        <x-sortable-header route="surplus.index" column="case" label="Case" />
                        <x-sortable-header route="surplus.index" column="claimant" label="Claimant" />
                        <x-sortable-header route="surplus.index" column="property" label="Property / parcel" />
                        <x-sortable-header route="surplus.index" column="assigned" label="Assigned" />
                        <x-sortable-header route="surplus.index" column="deadline" label="Claim deadline" />
                        @if($financial)<x-sortable-header route="surplus.index" column="surplus_fee" label="Surplus / expected fee" />@endif
                        <x-sortable-header route="surplus.index" column="status" label="Stage" />
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
                            <td class="px-5 py-4"><a href="{{ route('surplus.show',$case) }}" class="font-semibold hover:text-amber-600">{{ $case->case_number }}</a><p class="mt-1 text-xs text-slate-500">{{ $case->foreclosure_case_number ?: 'No foreclosure number' }}</p></td>
                            <td class="px-5 py-4">@if($case->claimantContact)<a href="{{ route('contacts.show',$case->claimantContact) }}" class="font-medium hover:text-amber-600">{{ $case->claimantContact->full_name }}</a><p class="mt-1 text-xs text-slate-500">{{ $case->claimantContact->company }}</p>@else<span class="text-slate-500">Unlinked</span>@endif</td>
                            <td class="px-5 py-4">@if($case->property)<a href="{{ route('properties.show',$case->property) }}" class="font-medium hover:text-amber-600">{{ $case->property->address }}</a>@else{{ $case->county ? $case->county.' County' : 'Unlinked' }}@endif<p class="mt-1 text-xs text-slate-500">{{ $case->parcel_id ?: 'No parcel ID' }}</p></td>
                            <td class="px-5 py-4">{{ $case->assignedUser?->name ?: 'Unassigned' }}</td>
                            <td class="px-5 py-4 {{ $case->claim_deadline?->isPast() && $case->status->isOpen() ? 'font-semibold text-rose-600' : '' }}">{{ $case->claim_deadline?->format('M j, Y') ?: '—' }}</td>
                            @if($financial)<td class="px-5 py-4">{{ $case->surplus_amount !== null ? '$'.number_format((float)$case->surplus_amount,2) : '—' }}<p class="mt-1 text-xs text-slate-500">Fee: {{ $case->expected_fee !== null ? '$'.number_format((float)$case->expected_fee,2) : '—' }}</p></td>@endif
                            <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold dark:bg-slate-800">{{ $case->status->label() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ ($financial ? 7 : 6) + ($canBulkUpdate ? 1 : 0) }}" class="px-6 py-14 text-center text-slate-500">No surplus cases match the current filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($canBulkUpdate)</form>@endif
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $cases->links() }}</div>
    </section>
</x-layouts.app>
