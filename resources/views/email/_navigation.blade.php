<div class="mb-6 flex flex-wrap gap-2">
    <a href="{{ route('email.index') }}" @class(['rounded-lg px-4 py-2 text-sm font-semibold','bg-slate-900 text-white dark:bg-amber-400 dark:text-slate-950' => ($active ?? '') === 'messages','border border-slate-300 dark:border-slate-700' => ($active ?? '') !== 'messages'])>Messages</a>
    @can('create', \App\Models\OutboundEmail::class)<a href="{{ route('email.create') }}" @class(['rounded-lg px-4 py-2 text-sm font-semibold','bg-slate-900 text-white dark:bg-amber-400 dark:text-slate-950' => ($active ?? '') === 'compose','border border-slate-300 dark:border-slate-700' => ($active ?? '') !== 'compose'])>Compose</a>@endcan
    <a href="{{ route('armory.email-templates.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-700">Templates</a>
    <a href="{{ route('email.signatures.index') }}" @class(['rounded-lg px-4 py-2 text-sm font-semibold','bg-slate-900 text-white dark:bg-amber-400 dark:text-slate-950' => ($active ?? '') === 'signatures','border border-slate-300 dark:border-slate-700' => ($active ?? '') !== 'signatures'])>Signatures</a>
</div>
