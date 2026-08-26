<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Sign in' }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-950 text-slate-100 antialiased">
<main class="grid min-h-screen place-items-center overflow-hidden p-6">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top_right,rgba(245,158,11,.16),transparent_38%),radial-gradient(circle_at_bottom_left,rgba(30,41,59,.7),transparent_42%)]"></div>
    <div class="relative w-full max-w-md">
        <div class="mb-8 flex items-center justify-center gap-3"><div class="grid h-12 w-12 place-items-center rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 text-xl font-black text-slate-950">V</div><div><p class="text-xl font-bold">VVR</p><p class="text-xs tracking-wide text-slate-400">COMMAND CENTER</p></div></div>
        <section class="rounded-2xl border border-slate-800 bg-slate-900/90 p-6 shadow-2xl backdrop-blur sm:p-8">{{ $slot }}</section>
        <p class="mt-6 text-center text-xs text-slate-500">Authorized Valor Venture Realty personnel only</p>
    </div>
</main>
</body></html>
