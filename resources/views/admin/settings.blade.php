@extends('warden::layout')

@section('title', 'Alert settings')
@section('heading', 'Alert settings')
@section('subheading', 'Global e-mail alert channel')

@section('content')
    @if(session('warden_status'))
        <div class="mb-5 rounded-lg border border-emerald-700/50 bg-emerald-900/20 px-4 py-3 text-sm text-emerald-300">
            {{ session('warden_status') }}
        </div>
    @endif
    @if(session('warden_error'))
        <div class="mb-5 rounded-lg border border-rose-700/50 bg-rose-900/20 px-4 py-3 text-sm text-rose-300">
            {{ session('warden_error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('warden.admin.settings.update') }}" class="max-w-2xl space-y-6">
        @csrf

        <div class="rounded-xl border border-ink-700 bg-ink-900 p-6 space-y-5">
            <div>
                <h3 class="text-sm font-semibold text-white">E-mail alerts</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Incident transitions are e-mailed through this app's configured mailer. Individual
                    projects can override these defaults on their edit page.
                </p>
            </div>

            <label class="flex items-center gap-3">
                <input type="checkbox" name="email_enabled" value="1" @checked(old('email_enabled', $settings->email_enabled))
                    class="h-4 w-4 rounded border-ink-700 bg-ink-850 text-brand-600 focus:ring-brand-500">
                <span class="text-sm text-slate-200">Enable e-mail alerts</span>
            </label>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Recipients</label>
                <textarea name="recipients" rows="3" placeholder="ops@example.com, oncall@example.com"
                    class="mt-1.5 w-full rounded-lg border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white focus:border-brand-500 focus:outline-none">{{ old('recipients', collect($settings->recipients ?? [])->implode(', ')) }}</textarea>
                <p class="mt-1 text-xs text-slate-500">Comma, semicolon or newline separated.</p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Minimum severity</label>
                    <select name="min_severity"
                        class="mt-1.5 w-full rounded-lg border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white focus:border-brand-500 focus:outline-none">
                        @foreach($severities as $sev)
                            <option value="{{ $sev }}" @selected(old('min_severity', $settings->min_severity) === $sev)>{{ ucfirst($sev) }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Only incidents at or above this severity are e-mailed.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Cooldown (seconds)</label>
                    <input type="number" name="cooldown" min="0" value="{{ old('cooldown', $settings->cooldown) }}"
                        class="mt-1.5 w-full rounded-lg border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white focus:border-brand-500 focus:outline-none">
                    <p class="mt-1 text-xs text-slate-500">Minimum gap between repeat alerts for the same incident.</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-500">
                Save settings
            </button>
            <a href="{{ route('warden.overview') }}" class="text-sm text-slate-400 transition hover:text-white">Cancel</a>
        </div>
    </form>
@endsection
