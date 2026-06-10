@extends('warden::layout')

@section('title', __('warden::admin.projects.title'))
@section('heading', __('warden::admin.projects.heading'))
@section('subheading', __('warden::admin.projects.subheading'))

@section('content')
    @php $timezones = \DateTimeZone::listIdentifiers(); @endphp
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
        <x-warden::button type="button" data-modal-open="wdn-add-project">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6"/></svg>
            {{ __('warden::admin.projects.add_project') }}
        </x-warden::button>
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
                                        <x-warden::button type="submit" variant="secondary" size="sm">{{ __('warden::admin.projects.run_now') }}</x-warden::button>
                                    </form>
                                </div>
                                <form method="POST" action="{{ route('warden.admin.projects.timezone', $project->id) }}" class="flex items-center gap-1.5">
                                    @csrf
                                    <span class="w-12 text-[10px] uppercase tracking-wider text-slate-600">{{ __('warden::admin.projects.label_tz') }}</span>
                                    {{-- Searchable select (zero-dep combobox) — same look as the form selects. --}}
                                    <div class="relative w-48" data-tz>
                                        <input type="hidden" name="timezone" value="{{ $project->timezone }}" data-tz-value>
                                        <button type="button" data-tz-trigger
                                            class="flex w-full items-center justify-between gap-2 rounded-xl border border-ink-700 bg-ink-850 px-3 py-1.5 text-left font-mono text-xs text-slate-200 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                                            <span class="truncate {{ $project->timezone ? '' : 'font-sans text-slate-500' }}" data-tz-label>{{ $project->timezone ?: __('warden::admin.projects.parent_default') }}</span>
                                            <svg class="h-3.5 w-3.5 shrink-0 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                                        </button>
                                        <div data-tz-panel class="fixed z-50 hidden w-64 overflow-hidden rounded-xl border border-ink-700 bg-ink-900 shadow-2xl shadow-black/50">
                                            <div class="border-b border-ink-700/70 p-2">
                                                <input type="text" data-tz-search autocomplete="off" placeholder="{{ __('warden::admin.projects.tz_search') }}"
                                                    class="w-full rounded-lg border border-ink-700 bg-ink-850 px-2.5 py-1.5 text-xs text-white outline-none transition placeholder:text-slate-600 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                                            </div>
                                            <ul data-tz-list class="max-h-56 overflow-auto p-1 font-mono text-xs"></ul>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <x-warden::button :href="route('warden.admin.projects.edit', $project->id)" variant="secondary" size="sm">
                                    {{ __('warden::admin.projects.btn_edit') }}
                                </x-warden::button>

                                {{-- Overflow menu (kebab) — secondary & destructive actions. --}}
                                <div class="relative" data-menu>
                                    <button type="button" data-menu-trigger aria-label="{{ __('warden::admin.projects.col_actions') }}"
                                        class="flex h-7 w-7 items-center justify-center rounded-lg border border-ink-700 text-slate-400 transition hover:border-brand-500 hover:text-white">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
                                    </button>
                                    <div data-menu-panel class="fixed z-50 hidden w-52 overflow-hidden rounded-xl border border-ink-700 bg-ink-900 py-1 shadow-2xl shadow-black/50">
                                        <form method="POST" action="{{ route('warden.admin.projects.credentials', $project->id) }}">
                                            @csrf
                                            <button class="block w-full px-3 py-2 text-left text-xs text-slate-300 transition hover:bg-ink-800 hover:text-white">{{ __('warden::admin.projects.btn_credentials') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('warden.admin.projects.rotate', $project->id) }}"
                                            data-confirm="{{ __('warden::admin.projects.confirm_rotate', ['name' => $project->name]) }}">
                                            @csrf
                                            <button class="block w-full px-3 py-2 text-left text-xs text-slate-300 transition hover:bg-ink-800 hover:text-white">{{ __('warden::admin.projects.btn_rotate') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('warden.admin.projects.toggle', $project->id) }}"
                                            data-confirm="{{ $project->active ? __('warden::admin.projects.confirm_deactivate', ['name' => $project->name]) : __('warden::admin.projects.confirm_activate', ['name' => $project->name]) }}">
                                            @csrf
                                            <button class="block w-full px-3 py-2 text-left text-xs text-slate-300 transition hover:bg-ink-800 hover:text-white">{{ $project->active ? __('warden::admin.projects.btn_deactivate') : __('warden::admin.projects.btn_activate') }}</button>
                                        </form>
                                        <div class="my-1 border-t border-ink-700/60"></div>
                                        <form method="POST" action="{{ route('warden.admin.projects.reset', $project->id) }}"
                                            data-confirm="{{ __('warden::admin.projects.confirm_reset', ['name' => $project->name]) }}">
                                            @csrf
                                            <button class="block w-full px-3 py-2 text-left text-xs text-rose-400 transition hover:bg-rose-500/10">{{ __('warden::admin.projects.btn_reset_metrics') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('warden.admin.projects.delete', $project->id) }}"
                                            data-confirm="{{ __('warden::admin.projects.confirm_delete', ['name' => $project->name]) }}">
                                            @csrf
                                            <button class="block w-full px-3 py-2 text-left text-xs text-rose-400 transition hover:bg-rose-500/10">{{ __('warden::admin.projects.btn_delete') }}</button>
                                        </form>
                                    </div>
                                </div>
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
                <x-warden::field :label="__('warden::admin.projects.modal_name_label')">
                    <x-warden::input name="name" required autofocus placeholder="{{ __('warden::admin.projects.modal_name_placeholder') }}" />
                </x-warden::field>
                <x-warden::field :label="__('warden::admin.projects.modal_slug_label')">
                    <x-warden::input name="slug" placeholder="my-app" />
                </x-warden::field>
                <div class="flex justify-end gap-2 pt-1">
                    <x-warden::button type="button" variant="ghost" data-modal-close="wdn-add-project">
                        {{ __('warden::common.cancel') }}
                    </x-warden::button>
                    <x-warden::button type="submit">
                        {{ __('warden::admin.projects.modal_create_btn') }}
                    </x-warden::button>
                </div>
            </form>
        </div>
    </div>

    @include('warden::partials.confirm-modal')

    {{-- Searchable timezone select — shared list + behaviour (zero-dep). --}}
    <script>
    window.__wdnTz = @json($timezones);
    window.__wdnTzDefault = @json(__('warden::admin.projects.parent_default'));
    (function () {
        var list = window.__wdnTz || [];
        var def = window.__wdnTzDefault || '';
        document.querySelectorAll('[data-tz]').forEach(function (root) {
            var trigger = root.querySelector('[data-tz-trigger]');
            var panel = root.querySelector('[data-tz-panel]');
            var search = root.querySelector('[data-tz-search]');
            var ul = root.querySelector('[data-tz-list]');
            var hidden = root.querySelector('[data-tz-value]');
            var label = root.querySelector('[data-tz-label]');
            var form = root.closest('form');

            function choose(value, text) {
                hidden.value = value;
                label.textContent = text || def;
                label.className = 'truncate' + (value ? '' : ' font-sans text-slate-500');
                close();
                if (form) { form.submit(); }
            }
            function render(q) {
                q = (q || '').toLowerCase();
                var items = [{ v: '', l: def }];
                for (var i = 0; i < list.length; i++) { items.push({ v: list[i], l: list[i] }); }
                if (q) { items = items.filter(function (it) { return it.l.toLowerCase().indexOf(q) > -1; }); }
                ul.innerHTML = '';
                items.slice(0, 250).forEach(function (it) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.textContent = it.l;
                    b.className = 'block w-full truncate rounded-md px-2.5 py-1.5 text-left transition hover:bg-ink-800 ' + (it.v === hidden.value ? 'text-brand-400' : 'text-slate-300');
                    b.addEventListener('click', function () { choose(it.v, it.l); });
                    var li = document.createElement('li');
                    li.appendChild(b);
                    ul.appendChild(li);
                });
            }
            function open() {
                panel.classList.remove('hidden');
                var r = trigger.getBoundingClientRect();
                var w = panel.offsetWidth;
                panel.style.top = (r.bottom + 6) + 'px';
                panel.style.left = Math.min(Math.max(8, r.left), window.innerWidth - w - 8) + 'px';
                search.value = '';
                render('');
                search.focus();
            }
            function close() { panel.classList.add('hidden'); }

            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                panel.classList.contains('hidden') ? open() : close();
            });
            search.addEventListener('input', function () { render(search.value); });
            panel.addEventListener('click', function (e) { e.stopPropagation(); });
            document.addEventListener('click', close);
            document.addEventListener('scroll', close, true);
            window.addEventListener('resize', close);
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); } });
        });
    })();

    // Kebab overflow menus on the project rows. The panel is position:fixed and
    // placed by JS so the table's overflow never clips it.
    (function () {
        var open = null;
        function closeAll() { if (open) { open.classList.add('hidden'); open = null; } }
        document.querySelectorAll('[data-menu]').forEach(function (root) {
            var trigger = root.querySelector('[data-menu-trigger]');
            var panel = root.querySelector('[data-menu-panel]');
            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                var wasOpen = open === panel;
                closeAll();
                if (!wasOpen) {
                    panel.classList.remove('hidden');
                    var r = trigger.getBoundingClientRect();
                    panel.style.top = (r.bottom + 6) + 'px';
                    panel.style.left = Math.max(8, r.right - panel.offsetWidth) + 'px';
                    open = panel;
                }
            });
            panel.addEventListener('click', function (e) { e.stopPropagation(); });
        });
        document.addEventListener('click', closeAll);
        window.addEventListener('resize', closeAll);
        document.addEventListener('scroll', closeAll, true);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeAll(); } });
    })();
    </script>

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
