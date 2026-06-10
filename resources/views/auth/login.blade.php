<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · Warden</title>
    @include('warden::partials.stylesheet')
    <style>
        body{background:#080a0f}
        @include('warden::partials.supplemental-css')
    </style>
</head>
<body class="font-sans text-slate-300 antialiased">
<div class="flex min-h-screen items-center justify-center px-6">
    <div class="w-full max-w-sm rounded-2xl border border-ink-700 bg-ink-900 p-8 shadow-xl">
        <div class="mb-6 flex items-center gap-2.5">
            <span class="relative flex h-3 w-3">
                <span class="absolute inline-flex h-full w-full rounded-full bg-brand-500 opacity-60 animate-ping"></span>
                <span class="relative inline-flex h-3 w-3 rounded-full bg-brand-500"></span>
            </span>
            <span class="text-[15px] font-semibold tracking-tight text-white">Warden</span>
            <span class="ml-auto text-[10px] uppercase tracking-widest text-slate-600">APM</span>
        </div>

        <h1 class="mb-1 text-lg font-semibold text-white">Sign in</h1>
        <p class="mb-6 text-xs text-slate-500">Enter the dashboard password to continue.</p>

        @if(session('warden_error'))
            <div class="mb-4 rounded-lg border border-rose-700/60 bg-rose-500/10 px-3 py-2 text-xs text-rose-200">
                {{ session('warden_error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('warden.login') }}" class="space-y-4">
            @csrf
            <div>
                <label for="password" class="mb-1.5 block text-xs font-medium text-slate-400">Password</label>
                <input id="password" name="password" type="password" autofocus required autocomplete="current-password"
                       class="w-full rounded-lg border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white outline-none focus:border-brand-500">
            </div>
            <button type="submit"
                    class="w-full rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-brand-500">
                Sign in
            </button>
        </form>
    </div>
</div>
</body>
</html>
