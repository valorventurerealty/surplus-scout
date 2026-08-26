<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" x-data="{ dark: localStorage.theme === 'dark' || (!('theme' in localStorage) && matchMedia('(prefers-color-scheme: dark)').matches) }" x-bind:class="{ 'dark': dark }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Command Center' }} · {{ config('app.name') }}</title>
    <script>document.documentElement.classList.toggle('dark', localStorage.theme === 'dark' || (!('theme' in localStorage) && matchMedia('(prefers-color-scheme: dark)').matches));</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
<div x-data="{ mobileNav: false }" class="min-h-full">
    <aside class="fixed inset-y-0 left-0 z-40 hidden w-72 border-r border-slate-200 bg-white lg:block dark:border-slate-800 dark:bg-slate-900">
        @include('layouts.navigation')
    </aside>

    <div x-show="mobileNav" x-cloak class="fixed inset-0 z-50 lg:hidden" @keydown.escape.window="mobileNav = false">
        <button class="absolute inset-0 bg-slate-950/60" @click="mobileNav = false" aria-label="Close navigation"></button>
        <aside class="relative h-full w-72 bg-white shadow-2xl dark:bg-slate-900">
            @include('layouts.navigation')
        </aside>
    </div>

    <div class="lg:pl-72">
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 dark:border-slate-800 dark:bg-slate-900/90">
            <div class="flex items-center gap-3">
                <button @click="mobileNav = true" class="rounded-lg p-2 hover:bg-slate-100 lg:hidden dark:hover:bg-slate-800" aria-label="Open navigation">☰</button>
                <div>
                    <h1 class="text-base font-semibold">{{ $heading ?? 'Command Center' }}</h1>
                    @isset($subheading)<p class="hidden text-xs text-slate-500 sm:block dark:text-slate-400">{{ $subheading }}</p>@endisset
                </div>
            </div>
            <div class="flex items-center gap-2">
                @php($unreadNotifications = auth()->user()->unreadNotifications()->latest()->limit(5)->get())
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.outside="open = false" class="relative rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700" aria-label="Task reminders">🔔@if($unreadNotifications->isNotEmpty())<span class="absolute -right-1.5 -top-1.5 min-w-5 rounded-full bg-rose-600 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ auth()->user()->unreadNotifications()->count() }}</span>@endif</button>
                    <div x-show="open" x-cloak class="absolute right-0 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                        <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold dark:border-slate-800">Task reminders</div>
                        <div class="divide-y divide-slate-100 dark:divide-slate-800">@forelse($unreadNotifications as $notification)<form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf<button class="block w-full px-4 py-3 text-left hover:bg-slate-50 dark:hover:bg-slate-800"><span class="block text-sm font-medium">{{ data_get($notification->data, 'title', 'Task reminder') }}</span><span class="mt-1 block text-xs text-slate-500">Due {{ data_get($notification->data, 'due_at') ? \Illuminate\Support\Carbon::parse(data_get($notification->data, 'due_at'))->format('M j, Y g:i A') : 'date not set' }}</span></button></form>@empty<p class="px-4 py-6 text-center text-sm text-slate-500">No unread reminders.</p>@endforelse</div>
                        <a href="{{ route('tasks.index', ['assigned_user_id' => auth()->id()]) }}" class="block border-t border-slate-200 px-4 py-3 text-center text-xs font-semibold text-amber-700 dark:border-slate-800 dark:text-amber-400">View my tasks</a>
                    </div>
                </div>
                <button @click="dark = !dark; localStorage.theme = dark ? 'dark' : 'light'" class="rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700" aria-label="Toggle color theme"><span x-text="dark ? '☀' : '☾'"></span></button>
                <div class="hidden text-right sm:block"><p class="text-sm font-medium">{{ auth()->user()->name }}</p><p class="text-xs text-slate-500">{{ auth()->user()->role->label() }}</p></div>
            </div>
        </header>

        <main class="p-4 sm:p-6 lg:p-8">
            @if (session('success'))<div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('success') }}</div>@endif
            {{ $slot }}
        </main>
    </div>
</div>
@livewireScripts
</body>
</html>
