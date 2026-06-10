@extends('warden::layout')

@section('title', __('warden::admin.projects.title'))
@section('heading', __('warden::admin.projects.heading'))
@section('subheading', __('warden::admin.projects.subheading'))

@section('content')
    @php $tzGroups = collect(\DateTimeZone::listIdentifiers())->groupBy(fn ($z) => str_contains($z, '/') ? explode('/', $z, 2)[0] : 'Other'); @endphp
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

    @if($creds = session('warden_credentials'))
        <div class="mb-6 rounded-xl border border-brand-600/50 bg-brand-600/10 p-5">
            <p class="text-sm font-medium text-white">{{ __('warden::admin.projects.credentials_shown_once', ['name' => $creds['name']]) }}</p>

            <p class="mt-3 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::admin.projects.option_a_title') }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ __('warden::admin.projects.option_a_description') }}</p>
            <pre class="mt-2 overflow-x-auto rounded-lg bg-ink-950 p-3 text-xs text-emerald-300">{{ $creds['command'] }}</pre>

            @isset($creds['env'])
                <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::admin.projects.option_b_title') }}</p>
                <p class="mt-1 text-xs text-slate-400">{!! __('warden::admin.projects.option_b_description') !!}</p>
                <pre class="mt-2 overflow-x-auto rounded-lg bg-ink-950 p-3 text-xs text-emerald-300">{{ $creds['env'] }}</pre>
            @endisset
        </div>
    @endif

    <div class="mb-6 flex justify-end">
        <button type="button" data-modal-open="wdn-add-project"
            class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-500">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6"/></svg>
            {{ __('warden::admin.projects.add_project') }}
        </button>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-ink-700/70">
        <table class="min-w-full divide-y divide-ink-700 text-sm">
            <thead class="bg-ink-850 text-left text-xs uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">{{ __('warden::admin.projects.col_name') }}</th>
                    <th class="px-4 py-3">{{ __('warden::admin.projects.col_slug') }}</th>
                    <th class="px-4 py-3">{{ __('warden::admin.projects.col_status') }}</th>
                    <th class="px-4 py-3">{{ __('warden::admin.projects.col_last_seen') }}</th>
                    <th class="px-4 py-3">{{ __('warden::admin.projects.col_settings') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('warden::admin.projects.col_actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink-700 bg-ink-900">
                @forelse($projects as $project)
                    <tr>
                        <td class="px-4 py-3 text-white">
                            <div>{{ $project->name }}</div>
                            @if($project->group || $project->tags->isNotEmpty())
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    @if($project->group)
                                        <span class="rounded-full border border-brand-600/40 bg-brand-600/10 px-2 py-0.5 text-[10px] text-brand-300">{{ $project->group->name }}</span>
                                    @endif
                                    @foreach($project->tags as $tag)
                                        <span class="rounded-full border border-ink-600 bg-ink-850 px-2 py-0.5 text-[10px] text-slate-400">{{ $tag->name }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-slate-400">{{ $project->slug }}</td>
                        <td class="px-4 py-3">
                            <span class="{{ $project->active ? 'text-emerald-400' : 'text-slate-500' }}">
                                {{ $project->active ? __('warden::admin.projects.status_active') : __('warden::admin.projects.status_inactive') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $project->last_seen_at?->diffForHumans() ?? __('warden::admin.projects.last_seen_never') }}</td>
                        <td class="px-4 py-3">
                            <div class="space-y-1.5">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="w-12 text-[10px] uppercase tracking-wider text-slate-600">{{ __('warden::admin.projects.label_audit') }}</span>
                                    <span class="text-xs text-slate-400">
                                        {{ $project->audit_frequency === 'off' ? __('warden::admin.projects.audit_off') : ucfirst($project->audit_frequency) }}
                                    </span>
                                    <form method="POST" action="{{ route('warden.admin.projects.audit-now', $project->id) }}">
                                        @csrf
                                        <button class="rounded-md border border-ink-600 px-2 py-1 text-[11px] text-slate-300 transition hover:border-brand-500 hover:text-white">{{ __('warden::admin.projects.run_now') }}</button>
                                    </form>
                                </div>
                                <form method="POST" action="{{ route('warden.admin.projects.timezone', $project->id) }}" class="flex items-center gap-1.5">
                                    @csrf
                                    <span class="w-12 text-[10px] uppercase tracking-wider text-slate-600">{{ __('warden::admin.projects.label_tz') }}</span>
                                    <select name="timezone" onchange="this.form.submit()"
                                        class="rounded-md border-ink-600 bg-ink-900 text-xs text-slate-300 focus:border-brand-500 focus:ring-brand-500">
                                        <option value="" @selected(($project->timezone ?? '') === '')>{{ __('warden::admin.projects.parent_default') }}</option>
                                        @foreach($tzGroups as $region => $zones)
                                            <optgroup label="{{ $region }}">
                                                @foreach($zones as $z)
                                                    <option value="{{ $z }}" @selected((string) ($project->timezone ?? '') === $z)>{{ $z }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </form>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('warden.admin.projects.edit', $project->id) }}"
                                    class="rounded-md border border-ink-600 px-2.5 py-1 text-xs text-slate-300 transition hover:border-brand-500 hover:text-white">
                                    {{ __('warden::admin.projects.btn_edit') }}
                                </a>
                                <form method="POST" action="{{ route('warden.admin.projects.credentials', $project->id) }}">
                                    @csrf
                                    <button class="rounded-md border border-ink-600 px-2.5 py-1 text-xs text-slate-300 transition hover:border-brand-500 hover:text-white">
                                        {{ __('warden::admin.projects.btn_credentials') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('warden.admin.projects.rotate', $project->id) }}"
                                    data-confirm="{{ __('warden::admin.projects.confirm_rotate', ['name' => $project->name]) }}">
                                    @csrf
                                    <button class="rounded-md border border-ink-600 px-2.5 py-1 text-xs text-slate-300 transition hover:border-brand-500 hover:text-white">
                                        {{ __('warden::admin.projects.btn_rotate') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('warden.admin.projects.toggle', $project->id) }}"
                                    data-confirm="{{ $project->active ? __('warden::admin.projects.confirm_deactivate', ['name' => $project->name]) : __('warden::admin.projects.confirm_activate', ['name' => $project->name]) }}">
                                    @csrf
                                    <button class="rounded-md border border-ink-600 px-2.5 py-1 text-xs text-slate-300 transition hover:border-brand-500 hover:text-white">
                                        {{ $project->active ? __('warden::admin.projects.btn_deactivate') : __('warden::admin.projects.btn_activate') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('warden.admin.projects.reset', $project->id) }}"
                                    data-confirm="{{ __('warden::admin.projects.confirm_reset', ['name' => $project->name]) }}">
                                    @csrf
                                    <button class="rounded-md border border-rose-700/60 px-2.5 py-1 text-xs text-rose-300 transition hover:border-rose-500 hover:text-rose-200">
                                        {{ __('warden::admin.projects.btn_reset_metrics') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('warden.admin.projects.delete', $project->id) }}"
                                    data-confirm="{{ __('warden::admin.projects.confirm_delete', ['name' => $project->name]) }}">
                                    @csrf
                                    <button class="rounded-md border border-rose-700/60 bg-rose-600/10 px-2.5 py-1 text-xs text-rose-300 transition hover:border-rose-500 hover:bg-rose-600/20 hover:text-rose-200">
                                        {{ __('warden::admin.projects.btn_delete') }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-600">{{ __('warden::admin.projects.no_projects') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Add project modal --}}
    <div id="wdn-add-project" style="display:none"
         class="fixed inset-0 z-50 items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true">
        <div class="w-full max-w-md rounded-xl border border-ink-700 bg-ink-850 p-5 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-white">{{ __('warden::admin.projects.modal_add_title') }}</h3>
                <button type="button" data-modal-close="wdn-add-project" class="text-slate-500 transition hover:text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('warden.admin.projects.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs text-slate-500">{{ __('warden::admin.projects.modal_name_label') }}</label>
                    <input name="name" required autofocus
                        class="mt-1 w-full rounded-lg border-ink-600 bg-ink-900 text-sm text-white focus:border-brand-500 focus:ring-brand-500"
                        placeholder="{{ __('warden::admin.projects.modal_name_placeholder') }}">
                </div>
                <div>
                    <label class="block text-xs text-slate-500">{{ __('warden::admin.projects.modal_slug_label') }}</label>
                    <input name="slug"
                        class="mt-1 w-full rounded-lg border-ink-600 bg-ink-900 text-sm text-white focus:border-brand-500 focus:ring-brand-500"
                        placeholder="my-app">
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" data-modal-close="wdn-add-project"
                        class="rounded-lg border border-ink-600 px-3 py-1.5 text-sm text-slate-300 transition hover:border-slate-500 hover:text-white">
                        {{ __('warden::common.cancel') }}
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-brand-600 px-4 py-1.5 text-sm font-medium text-white transition hover:bg-brand-500">
                        {{ __('warden::admin.projects.modal_create_btn') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @include('warden::partials.confirm-modal')

    <script>
    (function () {
        function toggle(id, show) {
            var el = document.getElementById(id);
            if (el) { el.style.display = show ? 'flex' : 'none'; }
        }
        document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
            btn.addEventListener('click', function () { toggle(btn.getAttribute('data-modal-open'), true); });
        });
        document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
            btn.addEventListener('click', function () { toggle(btn.getAttribute('data-modal-close'), false); });
        });
        var addModal = document.getElementById('wdn-add-project');
        if (addModal) {
            addModal.addEventListener('click', function (e) { if (e.target === addModal) { addModal.style.display = 'none'; } });
        }
        @if(session('warden_error'))
            toggle('wdn-add-project', true);
        @endif
    })();
    </script>
@endsection
