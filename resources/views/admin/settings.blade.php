@extends('warden::layout')

@section('title', __('warden::admin.settings.title'))
@section('heading', __('warden::admin.settings.heading'))
@section('subheading', __('warden::admin.settings.subheading'))

@section('content')
    @if(session('warden_status'))
        <div class="mb-5 rounded-xl border border-emerald-700/50 bg-emerald-900/20 px-4 py-3 text-sm text-emerald-300">
            {{ session('warden_status') }}
        </div>
    @endif
    @if(session('warden_error'))
        <div class="mb-5 rounded-xl border border-rose-700/50 bg-rose-900/20 px-4 py-3 text-sm text-rose-300">
            {{ session('warden_error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('warden.admin.settings.update') }}" class="max-w-2xl space-y-6">
        @csrf

        <div class="rounded-2xl border border-ink-700/70 bg-ink-900 shadow-lg shadow-black/10 p-6 space-y-5">
            <div>
                <h3 class="text-sm font-semibold text-white">{{ __('warden::admin.settings.section_email') }}</h3>
                <p class="mt-1 text-xs text-slate-500">
                    {{ __('warden::admin.settings.email_help') }}
                </p>
            </div>

            <label class="flex items-center gap-3">
                <input type="checkbox" name="email_enabled" value="1" @checked(old('email_enabled', $settings->email_enabled))
                    class="h-4 w-4 rounded border-ink-700 bg-ink-850 text-brand-600 focus:ring-brand-500">
                <span class="text-sm text-slate-200">{{ __('warden::admin.settings.email_enabled_label') }}</span>
            </label>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::admin.settings.recipients_label') }}</label>
                <textarea name="recipients" rows="3" placeholder="{{ __('warden::admin.settings.recipients_placeholder') }}"
                    class="mt-1.5 w-full rounded-xl border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">{{ old('recipients', collect($settings->recipients ?? [])->implode(', ')) }}</textarea>
                <p class="mt-1 text-xs text-slate-500">{{ __('warden::admin.settings.recipients_help') }}</p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::admin.settings.min_severity_label') }}</label>
                    <select name="min_severity"
                        class="mt-1.5 w-full rounded-xl border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                        @foreach($severities as $sev)
                            <option value="{{ $sev }}" @selected(old('min_severity', $settings->min_severity) === $sev)>{{ ucfirst($sev) }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">{{ __('warden::admin.settings.min_severity_help') }}</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::admin.settings.cooldown_label') }}</label>
                    <input type="number" name="cooldown" min="0" value="{{ old('cooldown', $settings->cooldown) }}"
                        class="mt-1.5 w-full rounded-xl border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                    <p class="mt-1 text-xs text-slate-500">{{ __('warden::admin.settings.cooldown_help') }}</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <x-warden::button type="submit">
                {{ __('warden::admin.settings.save_btn') }}
            </x-warden::button>
            <x-warden::button :href="route('warden.overview')" variant="ghost">{{ __('warden::common.cancel') }}</x-warden::button>
        </div>
    </form>
@endsection
