@php
    use VictorStochero\Warden\Dashboard\Format;
    $navProjects = \VictorStochero\Warden\Models\Project::query()->orderBy('name')->get(['id', 'name', 'slug']);
    // Only the project pages (which carry a {project} route parameter) highlight a
    // sidebar project. Admin views @extends this layout and loop `@foreach($projects
    // as $project)`, so the loop variable leaks in via @extends' get_defined_vars()
    // and would otherwise mark the last looped project active. Gate on the route.
    $activeProject = request()->route('project') ? ($project ?? null) : null;
    $activeSection = $section ?? ($active ?? null);
    $wardenVersion = \VictorStochero\Warden\Support\Version::current();
    $refresh = $refresh ?? 0;
    $ranges = $ranges ?? [];
    $currentRange = $range ?? request()->query('range', '1h');

    // Real-time transport (§5.4): on a project page we poll the cursor endpoint
    // and only refresh when it moves — no blind full-page reload. Other pages
    // (overview, admin) fall back to the simple meta refresh below.
    $streamUrl = ($activeProject ?? null)
        ? route('warden.project.stream', ['project' => $activeProject->slug, 'range' => $currentRange])
        : (request()->routeIs('warden.overview')
            ? route('warden.overview.stream', request()->only('group', 'tag'))
            : null);

    $groups = [
        'overview' => [
            'label' => __('warden::nav.groups.overview'),
            'items' => [
                'overview' => [__('warden::nav.sections.overview'), 'warden.project', []],
            ],
        ],
        'performance' => [
            'label' => __('warden::nav.groups.performance'),
            'items' => [
                'requests' => [__('warden::nav.sections.requests'), 'warden.project.section', ['section' => 'requests']],
                'database' => [__('warden::nav.sections.database'), 'warden.project.section', ['section' => 'database']],
                'jobs'     => [__('warden::nav.sections.jobs'), 'warden.project.section', ['section' => 'jobs']],
                'http'     => [__('warden::nav.sections.http'), 'warden.project.section', ['section' => 'http']],
                'schedule' => [__('warden::nav.sections.schedule'), 'warden.project.section', ['section' => 'schedule']],
            ],
        ],
        'reliability' => [
            'label' => __('warden::nav.groups.reliability'),
            'items' => [
                'errors'    => [__('warden::nav.sections.errors'), 'warden.project.section', ['section' => 'errors']],
                'issues'    => [__('warden::nav.issues'), 'warden.issues', []],
                'incidents' => [__('warden::nav.incidents'), 'warden.incidents', []],
                'uptime'    => [__('warden::nav.sections.uptime'), 'warden.project.section', ['section' => 'uptime']],
            ],
        ],
        'diagnostics' => [
            'label' => __('warden::nav.groups.diagnostics'),
            'items' => [
                'traces' => [__('warden::nav.traces'), 'warden.traces', []],
                'logs'   => [__('warden::nav.sections.logs'), 'warden.project.section', ['section' => 'logs']],
            ],
        ],
        'system' => [
            'label' => __('warden::nav.groups.system'),
            'items' => [
                'host'     => [__('warden::nav.sections.host'), 'warden.project.section', ['section' => 'host']],
                'mail'     => [__('warden::nav.sections.mail'), 'warden.project.section', ['section' => 'mail']],
                'security' => [__('warden::nav.sections.security'), 'warden.project.section', ['section' => 'security']],
                'delivery' => [__('warden::nav.sections.delivery'), 'warden.project.section', ['section' => 'delivery']],
            ],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if($refresh > 0 && ($autoRefresh ?? true) && ! $streamUrl)
        <meta http-equiv="refresh" content="{{ $refresh }}">
    @endif
    <title>@yield('title', 'Warden') · Warden</title>
    @include('warden::partials.favicon')
    @include('warden::partials.stylesheet')
    <style>
        ::-webkit-scrollbar{width:10px;height:10px}
        ::-webkit-scrollbar-track{background:transparent}
        ::-webkit-scrollbar-thumb{background:#232C42;border-radius:6px;border:2px solid #0A0E18}
        body{background:#070A12}
        .glass{background:linear-gradient(180deg,rgba(17,23,38,.6),rgba(10,14,24,.6));backdrop-filter:blur(8px)}

        /* Collapsible sidebar (icon rail on desktop) + off-canvas drawer on
           small screens. Pure CSS toggled by root classes, no build step. */
        #wdn-sidebar{transition:transform .2s ease,width .2s ease}
        #wdn-main{transition:padding-left .2s ease}
        @media (min-width:1024px){#wdn-main{padding-left:16rem}}
        /* Desktop rail: narrow to icons only. */
        @media (min-width:1024px){
            .wdn-rail #wdn-sidebar{width:4rem}
            .wdn-rail #wdn-main{padding-left:4rem}
            .wdn-rail #wdn-sidebar .wdn-label{display:none}
            .wdn-rail #wdn-sidebar .wdn-railhide{display:none}
            .wdn-rail #wdn-sidebar .wdn-navitem{justify-content:center;padding-left:0;padding-right:0}
            .wdn-rail #wdn-sidebar .wdn-railcenter{justify-content:center}
        }
        /* Mobile: hidden off-canvas, slides in when open. */
        @media (max-width:1023.98px){#wdn-sidebar{transform:translateX(-100%)}.wdn-open #wdn-sidebar{transform:translateX(0)}.wdn-open #wdn-backdrop{display:block}}

        /* Right-hand "Related" panel: ~320px on xl+, collapses to a thin rail
           that shows only the expand button. Root class toggled & persisted by
           the JS below, same pattern as the left sidebar rail. */
        #wdn-related{width:20rem;transition:width .2s ease}
        .wdn-related-only-collapsed{display:none}
        .wdn-related-collapsed #wdn-related{width:3.25rem}
        .wdn-related-collapsed #wdn-related .wdn-related-body{display:none}
        .wdn-related-collapsed #wdn-related .wdn-related-only-collapsed{display:flex}

        @include('warden::partials.supplemental-css')
    </style>
</head>
<body class="font-sans text-slate-300 antialiased">
<div aria-hidden="true" class="pointer-events-none fixed inset-x-0 top-0 z-0 h-80 bg-gradient-to-b from-brand-600/[0.07] via-brand-600/[0.02] to-transparent"></div>
<div id="wdn-root" class="relative flex min-h-screen">

    {{-- Mobile drawer backdrop --}}
    <div id="wdn-backdrop" class="fixed inset-0 z-30 hidden bg-black/60 backdrop-blur-sm lg:!hidden"></div>

    {{-- Sidebar --}}
    <aside id="wdn-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-ink-700 bg-ink-900">
        <a href="{{ route('warden.overview') }}" class="wdn-railcenter flex items-center gap-2.5 px-5 h-16 border-b border-ink-700">
            <svg viewBox="0 0 96 96" class="h-7 w-7 shrink-0 text-brand-500" fill="none" aria-hidden="true">
                <mask id="wMarkNav"><rect width="96" height="96" fill="black"/><path d="M18 17 H78 V45 C78 63 66 77 48 85 C30 77 18 63 18 45 Z" fill="white"/><path d="M29 35 L39 65 L48 47 L57 65 L67 35" fill="none" stroke="black" stroke-width="6.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="48" cy="27" r="2.6" fill="black"/></mask>
                <rect width="96" height="96" fill="currentColor" mask="url(#wMarkNav)"/>
            </svg>
            <span class="wdn-label wdn-wordmark text-[15px] text-white">Warden</span>
            <span class="wdn-label wdn-eyebrow ml-auto text-[10px] text-slate-500">{{ __('warden::nav.brand_tag') }}</span>
        </a>

        {{-- Getting-started hint + language switcher. Kept OUTSIDE the scrolling
             nav below so the popover isn't clipped by its overflow. --}}
        <div class="wdn-railcenter relative z-10 flex items-center justify-between gap-2 border-b border-ink-700/70 px-4 py-2.5">
            <div class="relative" data-wdn-help>
                <button type="button" data-wdn-help-toggle aria-label="{{ __('warden::nav.help') }}" title="{{ __('warden::nav.help') }}"
                    class="flex h-6 w-6 items-center justify-center rounded-full border border-ink-700 text-slate-400 transition hover:border-brand-500/60 hover:text-white">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </button>
                <div data-wdn-help-panel style="display:none"
                    class="absolute left-0 top-10 z-40 w-72 rounded-xl border border-brand-600/40 bg-ink-850 p-5 shadow-2xl shadow-black/60">
                    @include('warden::partials.getting-started')
                </div>
            </div>

            <div class="wdn-railhide flex items-center gap-0.5 text-[11px] font-medium" aria-label="{{ __('warden::nav.language') }}">
                @foreach(\VictorStochero\Warden\Support\Locales::all() as $loc)
                    @php $code = ['en' => 'EN', 'pt_BR' => 'PT', 'es' => 'ES'][$loc] ?? strtoupper((string) $loc); @endphp
                    <a href="{{ route('warden.locale', $loc) }}"
                       class="rounded px-1.5 py-0.5 transition {{ app()->getLocale() === $loc ? 'bg-ink-700 text-white' : 'text-slate-500 hover:text-white' }}">{{ $code }}</a>
                @endforeach
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
            <a href="{{ route('warden.overview') }}" title="{{ __('warden::nav.overview') }}"
               class="wdn-navitem flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition
               {{ ! $activeProject ? 'bg-brand-600/10 text-white ring-1 ring-inset ring-brand-500/20' : 'text-slate-400 hover:bg-ink-800 hover:text-white' }}">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span class="wdn-label">{{ __('warden::nav.overview') }}</span>
            </a>

            @can('manageWarden')
                <a href="{{ route('warden.admin.projects') }}" title="{{ __('warden::nav.manage_projects') }}"
                   class="wdn-navitem flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-400 transition hover:bg-ink-800 hover:text-white">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    <span class="wdn-label">{{ __('warden::nav.manage_projects') }}</span>
                </a>
                <a href="{{ route('warden.admin.maintenance') }}" title="{{ __('warden::nav.maintenance') }}"
                   class="wdn-navitem flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-400 transition hover:bg-ink-800 hover:text-white">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span class="wdn-label">{{ __('warden::nav.maintenance') }}</span>
                </a>
                <a href="{{ route('warden.admin.settings') }}" title="{{ __('warden::nav.alert_settings') }}"
                   class="wdn-navitem flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-400 transition hover:bg-ink-800 hover:text-white">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <span class="wdn-label">{{ __('warden::nav.alert_settings') }}</span>
                </a>
                <a href="{{ route('warden.admin.audit') }}" title="{{ __('warden::admin.audit.heading') }}"
                   class="wdn-navitem flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-400 transition hover:bg-ink-800 hover:text-white">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span class="wdn-label">{{ __('warden::admin.audit.heading') }}</span>
                </a>
                <a href="{{ route('warden.admin.api-tokens') }}" title="{{ __('warden::admin.api_tokens.heading') }}"
                   class="wdn-navitem flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-400 transition hover:bg-ink-800 hover:text-white">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    <span class="wdn-label">{{ __('warden::admin.api_tokens.heading') }}</span>
                </a>
            @endcan

            <p class="wdn-railhide px-3 pt-4 pb-1 text-[10px] font-semibold uppercase tracking-widest text-slate-600">{{ __('warden::nav.projects') }}</p>

            @forelse($navProjects as $p)
                @php $isActive = $activeProject && $activeProject->id === $p->id; @endphp
                <a href="{{ route('warden.project', $p->slug) }}" title="{{ $p->name }}"
                   class="wdn-navitem flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition
                   {{ $isActive ? 'bg-brand-600/10 text-white font-medium ring-1 ring-inset ring-brand-500/20' : 'text-slate-400 hover:bg-ink-800 hover:text-white' }}">
                    <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $isActive ? 'bg-brand-400' : 'bg-slate-600' }}"></span>
                    <span class="wdn-label truncate">{{ $p->name }}</span>
                </a>

                @if($isActive)
                    <div class="wdn-railhide ml-3 my-1 border-l border-ink-700 pl-2 space-y-2">
                        @foreach($groups as $group)
                            <div class="space-y-0.5" role="group" aria-label="{{ $group['label'] }}">
                                <p class="px-3 pt-1 text-[9px] font-semibold uppercase tracking-widest text-slate-600">{{ $group['label'] }}</p>
                                @foreach($group['items'] as $key => [$label, $routeName, $params])
                                    <a href="{{ route($routeName, array_merge(['project' => $p->slug], $params, ['range' => $currentRange])) }}"
                                       class="block rounded-md px-3 py-1.5 text-[13px] transition
                                       {{ ($activeSection ?? 'overview') === $key ? 'text-brand-400 font-medium' : 'text-slate-500 hover:text-slate-200' }}">
                                        {{ $label }}
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif
            @empty
                <p class="px-3 py-2 text-[13px] text-slate-600">{{ __('warden::nav.no_projects') }}</p>
            @endforelse
        </nav>

        @if(session('warden_auth'))
            <div class="wdn-railhide border-t border-ink-700 px-5 py-3">
                <p class="text-[11px] text-slate-500">{{ __('warden::common.signed_in_as', ['role' => session('warden_auth_admin') ? __('warden::common.role_admin') : __('warden::common.role_viewer')]) }}</p>
                <div class="mt-1.5 flex items-center gap-3 text-[11px]">
                    @unless(session('warden_auth_admin'))
                        <a href="{{ route('warden.login') }}" class="text-amber-300 transition hover:text-amber-200">{{ __('warden::common.sign_in_as_admin') }}</a>
                    @endunless
                    <form method="POST" action="{{ route('warden.logout') }}">
                        @csrf
                        <button type="submit" class="text-slate-400 transition hover:text-white">{{ __('warden::common.sign_out') }}</button>
                    </form>
                </div>
                @if($wardenVersion)
                    <p class="mt-2 font-mono text-[10px] text-slate-600">{{ __('warden::common.version', ['version' => $wardenVersion]) }}</p>
                @endif
            </div>
        @else
            <div class="wdn-railhide border-t border-ink-700 px-5 py-3 text-[11px] text-slate-600">
                {{ __('warden::common.self_hosted') }}
                @if($wardenVersion)
                    <span class="font-mono text-slate-600"> · {{ __('warden::common.version', ['version' => $wardenVersion]) }}</span>
                @endif
            </div>
        @endif
    </aside>

    {{-- Main --}}
    <div id="wdn-main" class="min-w-0 flex-1">
        <header class="sticky top-0 z-10 flex h-16 items-center gap-3 border-b border-ink-700 glass px-4 sm:px-6 lg:px-8">
            <button type="button" data-wdn-sidebar-toggle aria-label="{{ __('warden::nav.toggle_sidebar') }}" title="{{ __('warden::nav.toggle_sidebar') }}"
                class="-ml-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-ink-800 hover:text-white">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
            </button>
            <div class="min-w-0">
                <h1 class="truncate text-[15px] font-semibold text-white">@yield('heading', __('warden::nav.overview'))</h1>
                @hasSection('subheading')
                    <p class="truncate text-xs text-slate-500">@yield('subheading')</p>
                @endif
            </div>

            <div class="ml-auto flex items-center gap-3">
                @if(! empty($ranges) && ($showRanges ?? true))
                    <div class="flex items-center rounded-lg border border-ink-700 bg-ink-850 p-0.5">
                        @foreach($ranges as $r)
                            <a href="{{ request()->fullUrlWithQuery(['range' => $r]) }}"
                               class="rounded-md px-2.5 py-1 text-xs font-medium transition
                               {{ $currentRange === $r ? 'bg-brand-600 text-white' : 'text-slate-400 hover:text-white' }}">{{ $r }}</a>
                        @endforeach
                    </div>
                @endif
                @if($refresh > 0 && ($autoRefresh ?? true))
                    <span class="flex items-center gap-1.5 text-[11px] text-slate-500">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span> {{ __('warden::common.live') }}
                    </span>
                @endif
            </div>
        </header>

        {{-- Content + optional right-hand "Related" panel. The aside is rendered
             only when a project view passes $related; admin/overview views don't,
             so they keep their exact prior single-column layout. Hidden below xl
             so it never crowds narrow viewports. --}}
        <div class="flex">
            <main class="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                @if(session('warden_auth') && ! session('warden_auth_admin'))
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-700/50 bg-amber-900/20 px-4 py-2.5 text-sm text-amber-200">
                        <span>{{ __('warden::common.read_only_notice') }}</span>
                        <a href="{{ route('warden.login') }}"
                           class="shrink-0 rounded-md border border-amber-600/60 px-3 py-1 text-xs font-medium text-amber-100 transition hover:bg-amber-600/20">
                            {{ __('warden::common.sign_in_as_admin') }}
                        </a>
                    </div>
                @endif
                @yield('content')
            </main>

            @isset($related)
                <aside id="wdn-related" class="hidden shrink-0 flex-col self-stretch border-l border-ink-700 bg-ink-900 xl:flex">
                    @include('warden::partials.related-panel', ['related' => $related])
                </aside>
            @endisset
        </div>
    </div>
</div>
<script>
(function () {
    var wrap = document.querySelector('[data-wdn-help]');
    if (!wrap) { return; }
    var toggle = wrap.querySelector('[data-wdn-help-toggle]');
    var panel = wrap.querySelector('[data-wdn-help-panel]');
    function close() { panel.style.display = 'none'; }
    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
    });
    panel.addEventListener('click', function (e) { e.stopPropagation(); });
    document.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); } });
})();

// Collapsible sidebar: persists the collapsed state on desktop; toggles an
// off-canvas drawer (with backdrop) on smaller screens.
(function () {
    var root = document.getElementById('wdn-root');
    var backdrop = document.getElementById('wdn-backdrop');
    if (!root) { return; }
    var KEY = 'wdn_sidebar_rail';
    function isDesktop() { return window.matchMedia('(min-width: 1024px)').matches; }

    if (localStorage.getItem(KEY) === '1') { root.classList.add('wdn-rail'); }

    function toggle() {
        if (isDesktop()) {
            root.classList.toggle('wdn-rail');
            localStorage.setItem(KEY, root.classList.contains('wdn-rail') ? '1' : '0');
        } else {
            root.classList.toggle('wdn-open');
        }
    }

    document.querySelectorAll('[data-wdn-sidebar-toggle]').forEach(function (b) {
        b.addEventListener('click', toggle);
    });
    if (backdrop) { backdrop.addEventListener('click', function () { root.classList.remove('wdn-open'); }); }
    window.addEventListener('resize', function () { if (isDesktop()) { root.classList.remove('wdn-open'); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { root.classList.remove('wdn-open'); } });
})();

// Collapsible "Related" panel: open by default; persists the collapsed state.
// Same localStorage pattern as the left sidebar rail. No-op when the aside is
// absent (admin/overview views).
(function () {
    var root = document.getElementById('wdn-root');
    var aside = document.getElementById('wdn-related');
    if (!root || !aside) { return; }
    var KEY = 'wdn_related_collapsed';

    if (localStorage.getItem(KEY) === '1') { root.classList.add('wdn-related-collapsed'); }

    function toggle() {
        root.classList.toggle('wdn-related-collapsed');
        localStorage.setItem(KEY, root.classList.contains('wdn-related-collapsed') ? '1' : '0');
    }

    document.querySelectorAll('[data-wdn-related-toggle], [data-wdn-related-expand]').forEach(function (b) {
        b.addEventListener('click', toggle);
    });
})();

@if($streamUrl && $refresh > 0 && ($autoRefresh ?? true))
// Real-time transport (§5.4): coalesced cursor polling. We poll the stream with
// the last ETag; a 304 means nothing moved (no reload, the DB isn't even read);
// a changed cursor refreshes the view. Replaces the blind full-page meta-refresh
// so an idle dashboard costs one cheap conditional GET per interval, not a reload.
(function () {
    var url = @js($streamUrl);
    var interval = {{ (int) $refresh }} * 1000;
    if (!url || interval < 1000) { return; }

    var etag = null, timer = null;

    function schedule() { timer = setTimeout(poll, interval); }

    function poll() {
        var headers = { 'Accept': 'application/json' };
        if (etag) { headers['If-None-Match'] = etag; }

        fetch(url, { headers: headers, credentials: 'same-origin' })
            .then(function (res) {
                if (res.status === 304) { return; }          // unchanged — stay put
                var next = res.headers.get('ETag');
                if (etag && next && next !== etag) {          // moved since baseline
                    window.location.reload();
                    return;
                }
                etag = next;                                  // first poll: baseline
            })
            .catch(function () {})                            // never break the host UI
            .finally(schedule);
    }

    // Don't hammer the parent while the tab is in the background.
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { clearTimeout(timer); } else { poll(); }
    });

    poll();
})();
@endif
</script>
</body>
</html>
