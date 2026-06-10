@extends('warden::layout')

@section('title', 'Edit project')
@section('heading', 'Edit project')
@section('subheading', $project->name)

@section('content')
    @if(session('warden_error'))
        <div class="mb-5 rounded-lg border border-rose-700/50 bg-rose-900/20 px-4 py-3 text-sm text-rose-300">
            {{ session('warden_error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('warden.admin.projects.update', $project) }}" class="max-w-2xl space-y-6">
        @csrf

        <div class="rounded-xl border border-ink-700 bg-ink-900 p-6 space-y-5">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Name</label>
                <input type="text" name="name" value="{{ old('name', $project->name) }}" required
                    class="mt-1.5 w-full rounded-lg border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white focus:border-brand-500 focus:outline-none">
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Client</label>
                    <input type="text" name="client" value="{{ old('client', $project->client) }}" placeholder="e.g. Acme Inc."
                        class="mt-1.5 w-full rounded-lg border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white focus:border-brand-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Contact</label>
                    <input type="text" name="contact" value="{{ old('contact', $project->contact) }}" placeholder="name or e-mail"
                        class="mt-1.5 w-full rounded-lg border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white focus:border-brand-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Group</label>
                <input type="text" name="group" list="wdn-groups" value="{{ old('group', $project->group?->name) }}" placeholder="type to create or pick"
                    class="mt-1.5 w-full rounded-lg border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white focus:border-brand-500 focus:outline-none">
                <datalist id="wdn-groups">
                    @foreach($groups as $g)
                        <option value="{{ $g->name }}"></option>
                    @endforeach
                </datalist>
                <p class="mt-1 text-xs text-slate-500">Projects with the same group are clustered on the overview. Leave empty for none.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Tags</label>
                <input type="text" name="tags" value="{{ old('tags', $project->tags->pluck('name')->implode(', ')) }}" placeholder="comma-separated, e.g. prod, billing"
                    class="mt-1.5 w-full rounded-lg border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white focus:border-brand-500 focus:outline-none">
                @if($allTags->isNotEmpty())
                    <p class="mt-1.5 text-xs text-slate-500">Existing:
                        {{ $allTags->pluck('name')->implode(', ') }}
                    </p>
                @endif
            </div>
        </div>

        @php
            $freq = old('audit_frequency', $project->audit_frequency);
            $weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        @endphp
        <div class="rounded-xl border border-ink-700 bg-ink-900 p-6 space-y-5">
            <div>
                <h3 class="text-sm font-semibold text-white">Intervals</h3>
                <p class="mt-1 text-xs text-slate-500">When the security audit runs, and the window for the uptime KPI. Times are in this project's timezone.</p>
            </div>

            {{-- Security audit schedule --}}
            <div class="grid gap-5 sm:grid-cols-3">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Audit frequency</label>
                    <select name="audit_frequency" id="wdn-audit-frequency"
                        class="mt-1.5 w-full rounded-lg border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white focus:border-brand-500 focus:outline-none">
                        @foreach(['off' => 'Off', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $val => $optLabel)
                            <option value="{{ $val }}" @selected($freq === $val)>{{ $optLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div data-audit-day data-freq="weekly" style="{{ $freq === 'weekly' ? '' : 'display:none' }}">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Day of week</label>
                    <select name="audit_day" @disabled($freq !== 'weekly')
                        class="mt-1.5 w-full rounded-lg border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white focus:border-brand-500 focus:outline-none">
                        @foreach($weekdays as $i => $label)
                            <option value="{{ $i }}" @selected($freq === 'weekly' && (string) old('audit_day', $project->audit_day) === (string) $i)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div data-audit-day data-freq="monthly" style="{{ $freq === 'monthly' ? '' : 'display:none' }}">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Day of month</label>
                    <select name="audit_day" @disabled($freq !== 'monthly')
                        class="mt-1.5 w-full rounded-lg border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white focus:border-brand-500 focus:outline-none">
                        @for($d = 1; $d <= 31; $d++)
                            <option value="{{ $d }}" @selected($freq === 'monthly' && (string) old('audit_day', $project->audit_day) === (string) $d)>{{ $d }}</option>
                        @endfor
                    </select>
                </div>

                <div data-audit-hour style="{{ $freq === 'off' ? 'display:none' : '' }}">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Hour</label>
                    <select name="audit_hour"
                        class="mt-1.5 w-full rounded-lg border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white focus:border-brand-500 focus:outline-none">
                        <option value="" @selected(old('audit_hour', $project->audit_hour) === null)>Any hour</option>
                        @for($h = 0; $h < 24; $h++)
                            <option value="{{ $h }}" @selected((string) old('audit_hour', $project->audit_hour) === (string) $h)>{{ sprintf('%02d:00', $h) }}</option>
                        @endfor
                    </select>
                </div>
            </div>

            {{-- Uptime KPI window --}}
            <div class="max-w-xs">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Uptime window</label>
                <select name="uptime_window"
                    class="mt-1.5 w-full rounded-lg border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white focus:border-brand-500 focus:outline-none">
                    @foreach(['24h' => 'Last 24 hours', '7d' => 'Last 7 days', '30d' => 'Last 30 days'] as $val => $optLabel)
                        <option value="{{ $val }}" @selected(old('uptime_window', $project->uptime_window) === $val)>{{ $optLabel }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Headline availability KPI on the project's Uptime section.</p>
            </div>
        </div>

        @php
            $alertOverride = $project->alert_email_enabled !== null
                || ! empty($project->alert_recipients)
                || $project->alert_min_severity !== null;
        @endphp
        <div class="rounded-xl border border-ink-700 bg-ink-900 p-6 space-y-5">
            <div>
                <h3 class="text-sm font-semibold text-white">Alerts</h3>
                <p class="mt-1 text-xs text-slate-500">Override the global e-mail alert settings for this project. Leave fields blank to inherit the global defaults.</p>
            </div>

            <label class="flex items-center gap-3">
                <input type="checkbox" name="alert_override" id="wdn-alert-override" value="1" @checked(old('alert_override', $alertOverride))
                    class="h-4 w-4 rounded border-ink-700 bg-ink-850 text-brand-600 focus:ring-brand-500">
                <span class="text-sm text-slate-200">Override e-mail alerts for this project</span>
            </label>

            <div data-alert-fields style="{{ $alertOverride ? '' : 'display:none' }}" class="space-y-5">
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="alert_email_enabled" value="1" @checked(old('alert_email_enabled', $project->alert_email_enabled))
                        class="h-4 w-4 rounded border-ink-700 bg-ink-850 text-brand-600 focus:ring-brand-500">
                    <span class="text-sm text-slate-200">Enable e-mail alerts</span>
                </label>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Recipients</label>
                    <textarea name="alert_recipients" rows="2" placeholder="leave blank to use global recipients"
                        class="mt-1.5 w-full rounded-lg border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white focus:border-brand-500 focus:outline-none">{{ old('alert_recipients', collect($project->alert_recipients ?? [])->implode(', ')) }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">Comma, semicolon or newline separated.</p>
                </div>

                <div class="max-w-xs">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Minimum severity</label>
                    <select name="alert_min_severity"
                        class="mt-1.5 w-full rounded-lg border border-ink-700 bg-ink-850 px-3 py-2 text-sm text-white focus:border-brand-500 focus:outline-none">
                        <option value="" @selected(old('alert_min_severity', $project->alert_min_severity) === null || old('alert_min_severity', $project->alert_min_severity) === '')>Inherit global</option>
                        @foreach(['info', 'warning', 'critical'] as $sev)
                            <option value="{{ $sev }}" @selected(old('alert_min_severity', $project->alert_min_severity) === $sev)>{{ ucfirst($sev) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-500">
                Save changes
            </button>
            <a href="{{ route('warden.admin.projects') }}" class="text-sm text-slate-400 transition hover:text-white">Cancel</a>
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
    </script>
@endsection
