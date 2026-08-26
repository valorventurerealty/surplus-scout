<x-layouts.app title="Email Templates" heading="Armory Email Templates" subheading="Reusable, governed email copy for VVR operations">
    @include('armory._navigation', ['active' => 'email-templates'])

    <section class="mb-6 rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-6 dark:border-amber-900/70 dark:from-amber-950/30 dark:to-slate-900">
        <h2 class="text-lg font-bold">Email template library</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">Create approved email subjects and messages for seller, buyer, surplus, follow-up, contract, and closing workflows. Templates can be copied for use now and are ready for a future permission-controlled email workflow.</p>
    </section>

    <div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
        <form method="GET" class="grid flex-1 gap-3 sm:grid-cols-[minmax(220px,1fr)_190px_150px_auto]">
            @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}">@endif
            @if(request('direction'))<input type="hidden" name="direction" value="{{ request('direction') }}">@endif
            <input name="search" value="{{ request('search') }}" placeholder="Search name, subject, or message" class="form-input mt-0">
            <select name="category" class="form-input mt-0"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->value }}" @selected(request('category') === $category->value)>{{ $category->label() }}</option>@endforeach</select>
            <select name="status" class="form-input mt-0"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select>
            <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium dark:border-slate-700">Filter</button>
        </form>
        @can('create', \App\Models\ArmoryEmailTemplate::class)<a href="{{ route('armory.email-templates.create') }}" class="rounded-lg bg-amber-400 px-4 py-2 text-center text-sm font-semibold text-slate-950 hover:bg-amber-300">+ Add email template</a>@endcan
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto"><table class="w-full min-w-[850px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60"><tr><x-sortable-header route="armory.email-templates.index" column="template" label="Template" /><x-sortable-header route="armory.email-templates.index" column="category" label="Category" /><x-sortable-header route="armory.email-templates.index" column="subject" label="Subject" /><x-sortable-header route="armory.email-templates.index" column="version" label="Version" /><x-sortable-header route="armory.email-templates.index" column="status" label="Status" /><x-sortable-header route="armory.email-templates.index" column="updated" label="Updated" /></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse($templates as $template)<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40"><td class="px-5 py-4"><a href="{{ route('armory.email-templates.show', $template) }}" class="font-semibold hover:text-amber-600">{{ $template->name }}</a><p class="mt-1 max-w-xs truncate text-xs text-slate-500">{{ $template->description ?: 'No description' }}</p></td><td class="px-5 py-4">{{ $template->category->label() }}</td><td class="px-5 py-4"><span class="block max-w-sm truncate">{{ $template->subject }}</span></td><td class="px-5 py-4">{{ $template->version_label }}</td><td class="px-5 py-4"><span @class(['rounded-full px-2 py-1 text-xs font-semibold','bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' => $template->status === \App\Enums\ArmoryEmailTemplateStatus::Active,'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' => $template->status === \App\Enums\ArmoryEmailTemplateStatus::Draft,'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $template->status === \App\Enums\ArmoryEmailTemplateStatus::Retired])>{{ $template->status->label() }}</span></td><td class="px-5 py-4 text-xs text-slate-500">{{ $template->updated_at->format('M j, Y') }}<span class="mt-1 block">{{ $template->updater?->name ?? $template->creator?->name ?? 'VVR' }}</span></td></tr>
            @empty<tr><td colspan="6" class="px-6 py-14 text-center text-slate-500">No email templates match the current filters.</td></tr>@endforelse
        </tbody></table></div>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $templates->links() }}</div>
    </section>
</x-layouts.app>
