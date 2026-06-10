<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('warden::auth.title') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('vendor/warden/warden-mark.svg') }}">
    @include('warden::partials.stylesheet')
    <style>body{background:#070A12}</style>
</head>
<body class="relative min-h-screen overflow-hidden font-sans text-slate-300 antialiased">
    {{-- Ambient brand glow --}}
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -top-48 left-1/2 h-[40rem] w-[40rem] -translate-x-1/2 rounded-full bg-brand-600/20 blur-[150px]"></div>
        <div class="absolute -bottom-48 -right-32 h-[30rem] w-[30rem] rounded-full bg-brand-500/10 blur-[130px]"></div>
        <div class="absolute inset-0 opacity-[0.4] [background-image:radial-gradient(circle_at_center,rgba(99,102,241,0.06)_1px,transparent_1px)] [background-size:22px_22px]"></div>
    </div>

    <div class="relative flex min-h-screen items-center justify-center px-6 py-12">
        <div class="w-full max-w-sm">
            {{-- Brand lockup --}}
            <div class="mb-8 flex items-center justify-center gap-3">
                <svg viewBox="0 0 96 96" class="h-10 w-10" fill="none" aria-hidden="true">
                    <defs>
                        <linearGradient id="wShieldLogin" x1="48" y1="12" x2="48" y2="86" gradientUnits="userSpaceOnUse">
                            <stop offset="0" stop-color="#3D85FF"/><stop offset="1" stop-color="#1F5FE0"/>
                        </linearGradient>
                    </defs>
                    <path d="M18 17 H78 V45 C78 63 66 77 48 85 C30 77 18 63 18 45 Z" fill="url(#wShieldLogin)" stroke="#5B97FF" stroke-width="1.5" stroke-opacity="0.5"/>
                    <path d="M21 20 H75" stroke="#FFFFFF" stroke-opacity="0.18" stroke-width="1.5" stroke-linecap="round"/>
                    <path d="M29 35 L39 65 L48 47 L57 65 L67 35" fill="none" stroke="#FFFFFF" stroke-width="6.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="48" cy="27" r="2.6" fill="#9BE6F5"/>
                </svg>
                <span class="wdn-wordmark text-xl text-white">Warden</span>
            </div>

            <div class="rounded-2xl border border-ink-700/80 bg-gradient-to-b from-ink-900 to-ink-850 p-8 shadow-2xl shadow-black/40">
                <h1 class="text-lg font-semibold tracking-tight text-white">{{ __('warden::auth.heading') }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ __('warden::auth.subheading') }}</p>

                @if(session('warden_error'))
                    <div class="mt-5 flex items-start gap-2.5 rounded-xl border border-rose-700/50 bg-rose-500/10 px-3.5 py-2.5 text-xs text-rose-200">
                        <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ session('warden_error') }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('warden.login') }}" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label for="password" class="mb-1.5 block text-xs font-medium text-slate-400">{{ __('warden::auth.password_label') }}</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </span>
                            <input id="password" name="password" type="password" autofocus required autocomplete="current-password"
                                   class="w-full rounded-xl border border-ink-700 bg-ink-850 py-2.5 pl-10 pr-3.5 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                        </div>
                    </div>
                    <button type="submit"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-3 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-600/25 transition hover:bg-brand-500 hover:shadow-brand-500/30">
                        {{ __('warden::auth.submit') }}
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </button>
                </form>
            </div>

            {{-- Language switcher --}}
            <div class="mt-6 flex items-center justify-center gap-1 text-[11px] font-medium" aria-label="{{ __('warden::nav.language') }}">
                @foreach(\VictorStochero\Warden\Support\Cast::arr(config('warden.dashboard.locales')) as $loc)
                    @php $code = ['en' => 'EN', 'pt_BR' => 'PT', 'es' => 'ES'][$loc] ?? strtoupper((string) $loc); @endphp
                    <a href="{{ route('warden.locale', $loc) }}"
                       class="rounded-md px-2 py-1 transition {{ app()->getLocale() === $loc ? 'bg-ink-800 text-white' : 'text-slate-500 hover:text-white' }}">{{ $code }}</a>
                @endforeach
            </div>

            <p class="mt-6 text-center text-[11px] text-slate-600">{{ __('warden::common.self_hosted') }}</p>
        </div>
    </div>
</body>
</html>
