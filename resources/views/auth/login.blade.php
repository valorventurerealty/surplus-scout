<x-layouts.guest title="Sign in">
    <h1 class="text-2xl font-bold">Welcome back</h1><p class="mt-2 text-sm text-slate-400">Sign in to the VVR operating system.</p>
    @if (session('status'))<div class="mt-5 rounded-lg bg-emerald-950 px-4 py-3 text-sm text-emerald-300">{{ session('status') }}</div>@endif
    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5">@csrf
        <div><label for="email" class="text-sm font-medium">Email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2.5 outline-none ring-amber-400 focus:ring-2"><x-form-error name="email" /></div>
        <div><div class="flex justify-between"><label for="password" class="text-sm font-medium">Password</label><a href="{{ route('password.request') }}" class="text-sm text-amber-400 hover:text-amber-300">Forgot password?</a></div><input id="password" name="password" type="password" autocomplete="current-password" required class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2.5 outline-none ring-amber-400 focus:ring-2"><x-form-error name="password" /></div>
        <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="remember" class="rounded border-slate-600 bg-slate-950 text-amber-500"> Keep me signed in</label>
        <button class="w-full rounded-lg bg-amber-400 px-4 py-3 font-semibold text-slate-950 hover:bg-amber-300">Sign in securely</button>
    </form>
</x-layouts.guest>
