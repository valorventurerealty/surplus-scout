@php
    $mailersUrl = (string) config('vvr.mailers_url');
    $mailersLink = filter_var($mailersUrl, FILTER_VALIDATE_URL) && parse_url($mailersUrl, PHP_URL_SCHEME) === 'https'
        ? [['label' => 'Mailers', 'url' => $mailersUrl, 'external' => true]]
        : [];
    $dailyCommand = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard'],
        ['label' => 'Tasks', 'route' => 'tasks.index', 'active' => 'task*'],
        ['label' => 'Calendar', 'route' => 'calendar.index', 'active' => 'calendar.*'],
        ['label' => 'Pipeline', 'route' => 'pipeline.index', 'active' => 'pipeline.*'],
    ];
    $communication = [
        ['label' => 'Contacts', 'route' => 'contacts.index', 'active' => 'contacts.*'],
        ['label' => 'Phone Calls', 'route' => 'phone-interactions.index', 'active' => 'phone-interactions.*'],
        ...(Route::has('website-chats.index') ? [['label' => 'Website Chats', 'route' => 'website-chats.index', 'active' => 'website-chats.*']] : []),
        ['label' => 'Email', 'route' => 'email.index', 'active' => 'email.*'],
        ...$mailersLink,
    ];
    $revenueOperations = [
        ...(auth()->user()->canViewSurplusCases() ? [['label' => 'Surplus', 'route' => 'surplus.index', 'active' => 'surplus.*']] : []),
        ...(auth()->user()->canViewPreAuctionAcquisitions() ? [['label' => 'PreTax Auctions', 'route' => 'pre-auction.index', 'active' => 'pre-auction.*']] : []),
        ['label' => 'Properties', 'route' => 'properties.index', 'active' => 'properties.*'],
        ['label' => 'Deals', 'route' => 'deals.index', 'active' => 'deals.*'],
    ];
    $managementTools = [];
    if (auth()->user()->canViewPropertyFinancials()) {
        $managementTools[] = ['label' => 'Financials', 'route' => 'financials.index', 'active' => 'financials.*'];
        $managementTools[] = ['label' => 'Projections', 'route' => 'projections.index', 'active' => 'projections.*'];
    }
    if (auth()->user()->canUseVvrAi()) {
        $managementTools[] = ['label' => 'VVR AI', 'route' => 'vvr-ai.index', 'active' => 'vvr-ai.*'];
    }
    $managementTools[] = ['label' => 'Sales Copilot', 'route' => 'sales-copilot.index', 'active' => 'sales-copilot.*'];
    if (auth()->user()->canViewSurplusCases()) {
        $managementTools[] = ['label' => 'Surplus Scout', 'route' => 'surplus-scout.index', 'active' => 'surplus-scout.*'];
    }
    $managementTools[] = ['label' => 'SOPs', 'route' => 'sops.index', 'active' => 'sops.*'];
    $managementTools[] = ['label' => 'Armory', 'route' => 'armory.index', 'active' => 'armory.*'];
    $driveUrl = (string) config('vvr.drive_url');
    if (filter_var($driveUrl, FILTER_VALIDATE_URL) && parse_url($driveUrl, PHP_URL_SCHEME) === 'https') {
        $managementTools[] = ['label' => 'Drive', 'url' => $driveUrl, 'external' => true];
    }
    $navigationGroups = [
        ['label' => 'Daily Command', 'items' => $dailyCommand],
        ['label' => 'Communication', 'items' => $communication],
        ['label' => 'Revenue / Operations', 'items' => $revenueOperations],
        ['label' => 'Management / Tools', 'items' => $managementTools],
    ];
@endphp
<div class="flex h-full flex-col">
    <div class="flex h-20 items-center gap-3 border-b border-slate-200 px-6 dark:border-slate-800">
        <div class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 font-black text-slate-950">V</div>
        <div><p class="font-bold tracking-tight">VVR</p><p class="text-xs text-slate-500 dark:text-slate-400">Command Center</p></div>
    </div>
    <nav class="min-h-0 flex-1 overflow-y-auto p-3" aria-label="Primary navigation">
        <div class="grid grid-cols-2 content-start gap-x-2 gap-y-4" data-compact-workspace-navigation>
        @foreach ($navigationGroups as $group)
            <section class="min-w-0" aria-labelledby="navigation-group-{{ $loop->index }}">
                <h2 id="navigation-group-{{ $loop->index }}" class="mb-1 flex min-h-9 items-end px-2 text-[10px] font-semibold uppercase leading-tight tracking-wider text-slate-400">{{ $group['label'] }}</h2>
                <div class="space-y-1">
                    @foreach ($group['items'] as $item)
                        @php($active = isset($item['active']) && request()->routeIs($item['active']))
                        <a href="{{ isset($item['route']) ? route($item['route']) : $item['url'] }}" @if($item['external'] ?? false) target="_blank" rel="noopener noreferrer" @endif @class(['flex min-h-9 items-center justify-between rounded-lg px-2 py-1.5 text-xs font-semibold leading-tight transition', 'bg-slate-900 text-white dark:bg-amber-400 dark:text-slate-950' => $active, 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' => ! $active])><span>{{ $item['label'] }}</span>@if($item['external'] ?? false)<span aria-hidden="true" class="ml-1 shrink-0 text-xs text-slate-400">↗</span><span class="sr-only">(opens in a new tab)</span>@elseif(isset($item['badge']))<span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-500 dark:bg-slate-800 dark:text-slate-300">{{ $item['badge'] }}</span>@endif</a>
                    @endforeach
                </div>
            </section>
        @endforeach
        </div>
    </nav>
    <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-200 p-4 dark:border-slate-800">@csrf<button class="w-full rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Sign out</button></form>
</div>
