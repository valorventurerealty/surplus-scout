@php($active = $active ?? '')
<nav class="mb-6 flex flex-wrap gap-2" aria-label="Armory sections">
    @foreach([
        ['scripts', route('armory.index'), 'Script Library'],
        ['sessions', route('armory.sessions.index'), 'Guided Sessions'],
        ['negotiations', route('armory.negotiations.index'), 'Negotiations'],
        ['email-templates', route('armory.email-templates.index'), 'Email Templates'],
        ['sales-copilot', route('sales-copilot.index'), 'VVR Sales Copilot'],
    ] as [$key, $url, $label])
        <a href="{{ $url }}" @class(['rounded-lg px-4 py-2 text-sm font-semibold','bg-slate-900 text-white dark:bg-amber-400 dark:text-slate-950'=>$active===$key,'border border-slate-300 dark:border-slate-700'=>$active!==$key])>{{ $label }}</a>
    @endforeach
</nav>
