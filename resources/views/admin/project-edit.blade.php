@extends('warden::layout')

@section('title', __('warden::admin.edit.title'))
@section('heading', __('warden::admin.edit.heading'))
@section('subheading', $project->name)

@section('content')
    @if(session('warden_error'))
        <div class="mb-5 rounded-xl border border-rose-700/50 bg-rose-900/20 px-4 py-3 text-sm text-rose-300">
            {{ session('warden_error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('warden.admin.projects.update', $project) }}" class="max-w-2xl space-y-6">
        @csrf

        <div class="rounded-2xl border border-ink-700/70 bg-ink-900 shadow-lg shadow-black/10 p-6 space-y-5">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::admin.edit.name_label') }}</label>
                <input type="text" name="name" value="{{ old('name', $project->name) }}" required
                    class="mt-1.5 w-full rounded-xl border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::admin.edit.client_label') }}</label>
                    <input type="text" name="client" value="{{ old('client', $project->client) }}" placeholder="{{ __('warden::admin.edit.client_placeholder') }}"
                        class="mt-1.5 w-full rounded-xl border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::admin.edit.contact_label') }}</label>
                    <input type="text" name="contact" value="{{ old('contact', $project->contact) }}" placeholder="{{ __('warden::admin.edit.contact_placeholder') }}"
                        class="mt-1.5 w-full rounded-xl border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::admin.edit.group_label') }}</label>
                <input type="text" name="group" list="wdn-groups" value="{{ old('group', $project->group?->name) }}" placeholder="{{ __('warden::admin.edit.group_placeholder') }}"
                    class="mt-1.5 w-full rounded-xl border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                <datalist id="wdn-groups">
                    @foreach($groups as $g)
                        <option value="{{ $g->name }}"></option>
                    @endforeach
                </datalist>
                <p class="mt-1 text-xs text-slate-500">{{ __('warden::admin.edit.group_help') }}</p>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::admin.edit.tags_label') }}</label>
                <input type="text" name="tags" value="{{ old('tags', $project->tags->pluck('name')->implode(', ')) }}" placeholder="{{ __('warden::admin.edit.tags_placeholder') }}"
                    class="mt-1.5 w-full rounded-xl border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                @if($allTags->isNotEmpty())
                    <p class="mt-1.5 text-xs text-slate-500">{{ __('warden::admin.edit.tags_existing') }}
                        {{ $allTags->pluck('name')->implode(', ') }}
                    </p>
                @endif
            </div>
        </div>

        @php
            $freq = old('audit_frequency', $project->audit_frequency);
            $weekdays = [
                0 => __('warden::admin.edit.weekday_0'),
                1 => __('warden::admin.edit.weekday_1'),
                2 => __('warden::admin.edit.weekday_2'),
                3 => __('warden::admin.edit.weekday_3'),
                4 => __('warden::admin.edit.weekday_4'),
                5 => __('warden::admin.edit.weekday_5'),
                6 => __('warden::admin.edit.weekday_6'),
            ];
        @endphp
        <div class="rounded-2xl border border-ink-700/70 bg-ink-900 shadow-lg shadow-black/10 p-6 space-y-5">
            <div>
                <h3 class="text-sm font-semibold text-white">{{ __('warden::admin.edit.section_intervals') }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ __('warden::admin.edit.intervals_help') }}</p>
            </div>

            {{-- Security audit schedule --}}
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::admin.edit.audit_frequency_label') }}</label>
                    <select name="audit_frequency" id="wdn-audit-frequency"
                        class="mt-1.5 w-full rounded-xl border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                        @foreach(['off' => __('warden::admin.edit.freq_off'), 'daily' => __('warden::admin.edit.freq_daily'), 'weekly' => __('warden::admin.edit.freq_weekly'), 'monthly' => __('warden::admin.edit.freq_monthly')] as $val => $optLabel)
                            <option value="{{ $val }}" @selected($freq === $val)>{{ $optLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div data-audit-day data-freq="weekly" style="{{ $freq === 'weekly' ? '' : 'display:none' }}">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::admin.edit.audit_day_of_week') }}</label>
                    <select name="audit_day" @disabled($freq !== 'weekly')
                        class="mt-1.5 w-full rounded-xl border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                        @foreach($weekdays as $i => $label)
                            <option value="{{ $i }}" @selected($freq === 'weekly' && (string) old('audit_day', $project->audit_day) === (string) $i)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div data-audit-day data-freq="monthly" style="{{ $freq === 'monthly' ? '' : 'display:none' }}">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::admin.edit.audit_day_of_month') }}</label>
                    <select name="audit_day" @disabled($freq !== 'monthly')
                        class="mt-1.5 w-full rounded-xl border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                        @for($d = 1; $d <= 31; $d++)
                            <option value="{{ $d }}" @selected($freq === 'monthly' && (string) old('audit_day', $project->audit_day) === (string) $d)>{{ $d }}</option>
                        @endfor
                    </select>
                </div>

                <div data-audit-hour style="{{ $freq === 'off' ? 'display:none' : '' }}">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::admin.edit.audit_hour_label') }}</label>
                    <select name="audit_hour"
                        class="mt-1.5 w-full rounded-xl border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                        <option value="" @selected(old('audit_hour', $project->audit_hour) === null)>{{ __('warden::admin.edit.audit_any_hour') }}</option>
                        @for($h = 0; $h < 24; $h++)
                            <option value="{{ $h }}" @selected((string) old('audit_hour', $project->audit_hour) === (string) $h)>{{ sprintf('%02d:00', $h) }}</option>
                        @endfor
                    </select>
                </div>
            </div>

            {{-- Uptime KPI window --}}
            <div class="max-w-xs">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::admin.edit.uptime_window_label') }}</label>
                <select name="uptime_window"
                    class="mt-1.5 w-full rounded-xl border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                    @foreach(['24h' => __('warden::admin.edit.uptime_24h'), '7d' => __('warden::admin.edit.uptime_7d'), '30d' => __('warden::admin.edit.uptime_30d')] as $val => $optLabel)
                        <option value="{{ $val }}" @selected(old('uptime_window', $project->uptime_window) === $val)>{{ $optLabel }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">{{ __('warden::admin.edit.uptime_help') }}</p>
            </div>
        </div>

        @php
            $alertOverride = $project->alert_email_enabled !== null
                || ! empty($project->alert_recipients)
                || $project->alert_min_severity !== null;
        @endphp
        <div class="rounded-2xl border border-ink-700/70 bg-ink-900 shadow-lg shadow-black/10 p-6 space-y-5">
            <div>
                <h3 class="text-sm font-semibold text-white">{{ __('warden::admin.edit.section_alerts') }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ __('warden::admin.edit.alerts_help') }}</p>
            </div>

            <label class="flex items-center gap-3">
                <input type="checkbox" name="alert_override" id="wdn-alert-override" value="1" @checked(old('alert_override', $alertOverride))
                    class="h-4 w-4 rounded border-ink-700 bg-ink-850 text-brand-600 focus:ring-brand-500">
                <span class="text-sm text-slate-200">{{ __('warden::admin.edit.alert_override_label') }}</span>
            </label>

            <div data-alert-fields style="{{ $alertOverride ? '' : 'display:none' }}" class="space-y-5">
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="alert_email_enabled" value="1" @checked(old('alert_email_enabled', $project->alert_email_enabled))
                        class="h-4 w-4 rounded border-ink-700 bg-ink-850 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm text-slate-200">{{ __('warden::admin.edit.alert_enabled_label') }}</span>
                </label>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::admin.edit.recipients_label') }}</label>
                    <textarea name="alert_recipients" rows="2" placeholder="{{ __('warden::admin.edit.recipients_placeholder') }}"
                        class="mt-1.5 w-full rounded-xl border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">{{ old('alert_recipients', collect($project->alert_recipients ?? [])->implode(', ')) }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">{{ __('warden::admin.edit.recipients_help') }}</p>
                </div>

                <div class="max-w-xs">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::admin.edit.min_severity_label') }}</label>
                    <select name="alert_min_severity"
                        class="mt-1.5 w-full rounded-xl border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                        <option value="" @selected(old('alert_min_severity', $project->alert_min_severity) === null || old('alert_min_severity', $project->alert_min_severity) === '')>{{ __('warden::admin.edit.min_severity_inherit') }}</option>
                        @foreach(['info', 'warning', 'critical'] as $sev)
                            <option value="{{ $sev }}" @selected(old('alert_min_severity', $project->alert_min_severity) === $sev)>{{ ucfirst($sev) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @php
            $cfg = is_array($project->config) ? $project->config : [];
            $cfgHostInterval = $cfg['host_interval'] ?? null;
            $cfgTraceRequest = $cfg['sample']['traces']['request'] ?? null;
            $cfgTraceJob = $cfg['sample']['traces']['job'] ?? null;
            $cfgSlowerMs = $cfg['sample']['always_keep']['slower_than_ms'] ?? null;
            $cfgRecorders = $cfg['recorders'] ?? null; // null = inherit, [] = explicitly none
            $availableRecorders = (array) config('warden.child.recorders', []);
            $defHostInterval = config('warden.child.host_interval');
            $defTraceRequest = config('warden.child.sample.traces.request');
            $defTraceJob = config('warden.child.sample.traces.job');
            $defSlowerMs = config('warden.child.sample.always_keep.slower_than_ms');
        @endphp
        <div class="rounded-2xl border border-ink-700/70 bg-ink-900 shadow-lg shadow-black/10 p-6 space-y-5">
            <div>
                <h3 class="text-sm font-semibold text-white">{{ __('warden::project.behaviour.title') }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ __('warden::project.behaviour.intro') }}</p>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                <x-warden::field :label="__('warden::project.behaviour.host_interval')" for="wdn-cfg-host-interval"
                    :hint="__('warden::project.behaviour.host_interval_hint')">
                    <x-warden::input type="number" min="1" step="1" id="wdn-cfg-host-interval"
                        name="config[host_interval]" class="mt-1.5"
                        value="{{ old('config.host_interval', $cfgHostInterval) }}"
                        placeholder="{{ $defHostInterval }}" />
                </x-warden::field>

                <x-warden::field :label="__('warden::project.behaviour.sample_request')" for="wdn-cfg-trace-request"
                    :hint="__('warden::project.behaviour.sample_request_hint')">
                    <x-warden::input type="number" min="0" max="1" step="0.01" id="wdn-cfg-trace-request"
                        name="config[sample][traces][request]" class="mt-1.5"
                        value="{{ old('config.sample.traces.request', $cfgTraceRequest) }}"
                        placeholder="{{ $defTraceRequest }}" />
                </x-warden::field>

                <x-warden::field :label="__('warden::project.behaviour.sample_job')" for="wdn-cfg-trace-job"
                    :hint="__('warden::project.behaviour.sample_job_hint')">
                    <x-warden::input type="number" min="0" max="1" step="0.01" id="wdn-cfg-trace-job"
                        name="config[sample][traces][job]" class="mt-1.5"
                        value="{{ old('config.sample.traces.job', $cfgTraceJob) }}"
                        placeholder="{{ $defTraceJob }}" />
                </x-warden::field>
            </div>

            <div class="max-w-xs">
                <x-warden::field :label="__('warden::project.behaviour.slower_ms')" for="wdn-cfg-slower-ms"
                    :hint="__('warden::project.behaviour.slower_ms_hint')">
                    <x-warden::input type="number" min="0" step="1" id="wdn-cfg-slower-ms"
                        name="config[sample][always_keep][slower_than_ms]" class="mt-1.5"
                        value="{{ old('config.sample.always_keep.slower_than_ms', $cfgSlowerMs) }}"
                        placeholder="{{ $defSlowerMs }}" />
                </x-warden::field>
            </div>

            @if($availableRecorders !== [])
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::project.behaviour.recorders') }}</label>
                    <p class="mt-1 text-xs text-slate-500">{{ __('warden::project.behaviour.recorders_hint') }}</p>
                    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                        @foreach($availableRecorders as $recorder)
                            <label class="flex items-center gap-2">
                                <x-warden::checkbox name="config[recorders][]" value="{{ $recorder }}"
                                    :checked="is_array($cfgRecorders) && in_array($recorder, $cfgRecorders, true)" />
                                <span class="text-sm text-slate-200">{{ $recorder }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        @php
            $cfgCapture = is_array($cfg['capture'] ?? null) ? $cfg['capture'] : [];
            $envOverrides = (array) $project->env_overrides;
            $piiLocked = in_array('capture.pii', $envOverrides, true);
            $mailBodyLocked = in_array('capture.mail_body', $envOverrides, true);
        @endphp
        <div class="rounded-2xl border border-ink-700/70 bg-ink-900 shadow-lg shadow-black/10 p-6 space-y-5">
            <div>
                <h3 class="text-sm font-semibold text-white">{{ __('warden::project.behaviour.capture') }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ __('warden::project.behaviour.capture_help') }}</p>
            </div>

            {{-- Capture PII --}}
            <div>
                <label class="flex items-center gap-2.5">
                    <x-warden::checkbox id="wdn-cfg-capture-pii" name="config[capture][pii]" value="1"
                        :checked="(bool) ($cfgCapture['pii'] ?? false)" :disabled="$piiLocked" />
                    <span class="text-sm text-slate-200">{{ __('warden::project.behaviour.capture_pii') }}</span>
                    <span class="flex h-4 w-4 cursor-help items-center justify-center rounded-full bg-ink-700 text-[10px] font-bold text-slate-300"
                        title="{{ __('warden::project.behaviour.capture_pii_hint') }}"
                        tabindex="0" role="img" aria-label="{{ __('warden::project.behaviour.capture_pii_hint') }}">?</span>
                    @if($piiLocked)
                        <span class="rounded-md bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-400 ring-1 ring-inset ring-amber-500/20">{{ __('warden::project.behaviour.capture_env_locked') }}</span>
                    @endif
                </label>
            </div>

            {{-- Capture mail body --}}
            <div>
                <label class="flex items-center gap-2.5">
                    <x-warden::checkbox id="wdn-cfg-capture-mail-body" name="config[capture][mail_body]" value="1"
                        :checked="(bool) ($cfgCapture['mail_body'] ?? false)" :disabled="$mailBodyLocked" />
                    <span class="text-sm text-slate-200">{{ __('warden::project.behaviour.capture_mail_body') }}</span>
                    <span class="flex h-4 w-4 cursor-help items-center justify-center rounded-full bg-ink-700 text-[10px] font-bold text-slate-300"
                        title="{{ __('warden::project.behaviour.capture_mail_body_hint') }}"
                        tabindex="0" role="img" aria-label="{{ __('warden::project.behaviour.capture_mail_body_hint') }}">?</span>
                    @if($mailBodyLocked)
                        <span class="rounded-md bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-400 ring-1 ring-inset ring-amber-500/20">{{ __('warden::project.behaviour.capture_env_locked') }}</span>
                    @endif
                </label>
            </div>

            {{-- Credential floor notice — no toggle; disable_credential_scrub is .env-only. --}}
            <div class="rounded-xl border border-ink-700/70 bg-ink-850/50 px-4 py-3 text-xs leading-relaxed text-slate-400">
                {{ __('warden::project.behaviour.capture_credential_floor') }}
            </div>
        </div>

        <div class="flex items-center gap-2">
            <x-warden::button type="submit">
                {{ __('warden::common.save_changes') }}
            </x-warden::button>
            <x-warden::button :href="route('warden.admin.projects')" variant="ghost">{{ __('warden::common.cancel') }}</x-warden::button>
        </div>
    </form>

    <script>
    (function () {
        var freq = document.getElementById('wdn-audit-frequency');
        if (!freq) { return; }
        function sync() {
            var value = freq.value;
            document.querySelectorAll('[data-audit-day]').forEach(function (el) {
                var match = el.getAttribute('data-freq') === value;
                el.style.display = match ? '' : 'none';
                el.querySelectorAll('select').forEach(function (s) { s.disabled = !match; });
            });
            var hour = document.querySelector('[data-audit-hour]');
            if (hour) { hour.style.display = value === 'off' ? 'none' : ''; }
        }
        freq.addEventListener('change', sync);
        sync();
    })();

    (function () {
        var toggle = document.getElementById('wdn-alert-override');
        var fields = document.querySelector('[data-alert-fields]');
        if (!toggle || !fields) { return; }
        function sync() { fields.style.display = toggle.checked ? '' : 'none'; }
        toggle.addEventListener('change', sync);
        sync();
    })();

    // Confirm before enabling PII capture; uncheck if the operator cancels.
    (function () {
        var pii = document.getElementById('wdn-cfg-capture-pii');
        if (!pii) { return; }
        pii.addEventListener('change', function () {
            if (pii.checked && !window.confirm(@js(__('warden::project.behaviour.capture_pii_confirm')))) {
                pii.checked = false;
            }
        });
    })();
    </script>
@endsection
