<aside class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <a href="{{ route('vvr-ai.index') }}" class="flex w-full items-center justify-center rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-500">+ New AI task</a>
    <p class="mb-2 mt-6 px-2 text-[11px] font-semibold uppercase tracking-widest text-slate-400">Your conversations</p>
    <div class="space-y-1">
        @forelse($conversations as $item)
            <a href="{{ route('vvr-ai.conversations.show', $item) }}" @class(['block rounded-lg px-3 py-2.5', 'bg-violet-50 dark:bg-violet-950/40' => isset($conversation) && $conversation->is($item), 'hover:bg-slate-50 dark:hover:bg-slate-800' => !isset($conversation) || !$conversation->is($item)])>
                <span class="block truncate text-sm font-medium">{{ $item->title }}</span>
                <span class="mt-1 block text-[11px] text-slate-500">{{ str($item->status)->replace('_', ' ')->title() }} · {{ $item->last_message_at?->diffForHumans() }}</span>
            </a>
        @empty
            <p class="px-3 py-4 text-xs text-slate-500">No AI tasks yet.</p>
        @endforelse
    </div>
</aside>
