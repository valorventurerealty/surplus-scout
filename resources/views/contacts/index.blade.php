<x-layouts.app title="Contacts" heading="Contacts" subheading="Sellers, surplus claimants, buyers, investors, builders, and partners">
    <div class="mb-6 space-y-4">
        <div class="flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
            <form method="GET" class="grid flex-1 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_150px_150px_130px_auto]">
                @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="direction" value="{{ request('direction', 'asc') }}">@endif
                <div><label for="search" class="sr-only">Search contacts</label><input id="search" name="search" value="{{ request('search') }}" placeholder="Search name, company, email, phone" class="form-input mt-0"></div>
                <div><label for="type" class="sr-only">Contact type</label><select id="type" name="type" class="form-input mt-0"><option value="">All types</option>@foreach(\App\Enums\ContactType::cases() as $type)@if(match($type) { \App\Enums\ContactType::Surplus => auth()->user()->canViewSurplusCases(), \App\Enums\ContactType::PreTaxAuctions => auth()->user()->canViewPreAuctionAcquisitions(), default => true })<option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>@endif @endforeach</select></div>
                <div><label for="status" class="sr-only">Contact status</label><select id="status" name="status" class="form-input mt-0"><option value="">All statuses</option>@foreach(\App\Enums\ContactStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
                <div><label for="per_page" class="sr-only">Contacts per page</label><select id="per_page" name="per_page" class="form-input mt-0">@foreach([20,50,100,250] as $size)<option value="{{ $size }}" @selected((int)request('per_page',20) === $size)>{{ $size }} per page</option>@endforeach</select></div>
                <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium dark:border-slate-700">Apply</button>
            </form>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('contacts.export') }}">@csrf<input type="hidden" name="mode" value="filtered">@foreach(request()->only(['search','type','status','sort','direction']) as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach<button class="rounded-lg border border-emerald-300 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-900 dark:text-emerald-400 dark:hover:bg-emerald-950">Export filtered</button></form>
                @can('create', \App\Models\Contact::class)<a href="{{ route('contacts.create') }}" class="rounded-lg bg-amber-400 px-4 py-2 text-center text-sm font-semibold text-slate-950 hover:bg-amber-300">+ Add contact</a>@endcan
            </div>
        </div>
        <p class="text-xs text-slate-500">Showing {{ $contacts->firstItem() ?? 0 }}–{{ $contacts->lastItem() ?? 0 }} of {{ number_format($contacts->total()) }} contacts. Exports include contact details, associated properties, visible Surplus cases, and open tasks.</p>
    </div>

    <div x-data="{ selected: [], pageIds: @js($contacts->pluck('id')->map(fn($id)=>(int)$id)->values()) }" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <form method="POST" action="{{ route('contacts.export') }}">@csrf<input type="hidden" name="mode" value="selected">
            <div class="flex flex-col justify-between gap-3 border-b border-slate-200 bg-slate-50 px-5 py-3 sm:flex-row sm:items-center dark:border-slate-800 dark:bg-slate-800/40">
                <p class="text-sm text-slate-600 dark:text-slate-300"><span x-text="selected.length">0</span> selected on this page</p>
                <button type="submit" :disabled="selected.length === 0" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-40">Export selected</button>
            </div>
            <div class="overflow-x-auto"><table class="w-full min-w-[1020px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60"><tr>
                <th class="w-12 px-5 py-3"><label class="sr-only" for="select_page_contacts">Select all contacts on this page</label><input id="select_page_contacts" type="checkbox" :checked="pageIds.length > 0 && pageIds.every(id => selected.includes(id))" @change="selected = $event.target.checked ? [...pageIds] : []" class="rounded border-slate-300"></th>
                <x-sortable-header route="contacts.index" column="name" label="Name" /><x-sortable-header route="contacts.index" column="company" label="Company" /><x-sortable-header route="contacts.index" column="email" label="Email" /><x-sortable-header route="contacts.index" column="associated_tasks" label="Associated tasks" /><x-sortable-header route="contacts.index" column="next_follow_up" label="Next follow-up" />
            </tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse($contacts as $contact)<tr class="align-top hover:bg-slate-50 dark:hover:bg-slate-800/40">
                <td class="px-5 py-4"><label class="sr-only" for="contact_{{ $contact->id }}">Select {{ $contact->full_name }}</label><input id="contact_{{ $contact->id }}" type="checkbox" name="contact_ids[]" value="{{ $contact->id }}" x-model.number="selected" class="rounded border-slate-300"></td>
                <td class="px-5 py-4"><a href="{{ route('contacts.show', $contact) }}" class="font-semibold hover:text-amber-600">{{ $contact->full_name }}</a><div class="mt-1 flex gap-1"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] dark:bg-slate-800">{{ $contact->type->label() }}</span><span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] dark:bg-slate-800">{{ $contact->status->label() }}</span></div></td>
                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $contact->company ?: '—' }}</td>
                <td class="px-5 py-4">@if($contact->email)<a href="mailto:{{ $contact->email }}" class="text-amber-700 hover:underline dark:text-amber-400">{{ $contact->email }}</a>@else<span class="text-slate-400">—</span>@endif</td>
                <td class="px-5 py-4"><a href="{{ route('contacts.show', $contact) }}#tasks" class="block hover:text-amber-600">@forelse($contact->tasks as $task)<span class="mb-1 block max-w-64 truncate text-xs">• {{ $task->title }}</span>@empty<span class="text-slate-400">No open tasks</span>@endforelse @if($contact->open_tasks_count > $contact->tasks->count())<span class="text-xs font-medium text-amber-600">+{{ $contact->open_tasks_count - $contact->tasks->count() }} more</span>@endif</a></td>
                <td class="px-5 py-4"><span @class(['font-medium text-rose-600 dark:text-rose-400' => $contact->next_follow_up_at?->isPast(), 'text-slate-500' => ! $contact->next_follow_up_at?->isPast()])>{{ $contact->next_follow_up_at?->format('M j, Y') ?? '—' }}</span>@if($contact->next_follow_up_at)<p class="mt-1 text-xs text-slate-400">{{ $contact->next_follow_up_at->format('g:i A') }}</p>@endif @if($contact->next_follow_up_purpose)<p class="mt-1 max-w-xs text-xs text-slate-500 dark:text-slate-400">{{ $contact->next_follow_up_purpose }}</p>@endif</td>
            </tr>@empty<tr><td colspan="6" class="px-5 py-14 text-center text-slate-500">No contacts match the current filters.</td></tr>@endforelse
            </tbody></table></div>
        </form>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $contacts->links() }}</div>
    </div>
</x-layouts.app>
