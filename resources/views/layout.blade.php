@php
    use VictorStochero\Warden\Dashboard\Format;
    $navProjects = \VictorStochero\Warden\Models\Project::query()->orderBy('name')->get(['id', 'name', 'slug']);
    $activeProject = $project ?? null;
    $activeSection = $section ?? ($active ?? null);
    $refresh = $refresh ?? 0;
    $ranges = $ranges ?? [];
    $currentRange = $range ?? request()->query('range', '1h');

    $sub = [
        'overview' => ['Overview', 'warden.project', []],
        'requests' => ['Requests', 'warden.project.section', ['section' => 'requests']],
        'errors'   => ['Errors', 'warden.project.section', ['section' => 'errors']],
        'queries'  => ['Queries', 'warden.project.section', ['section' => 'queries']],
        'jobs'     => ['Jobs & Queues', 'warden.project.section', ['section' => 'jobs']],
        'cache'    => ['Cache', 'warden.project.section', ['section' => 'cache']],
        'schedule' => ['Schedule', 'warden.project.section', ['section' => 'schedule']],
        'http'     => ['Outgoing HTTP', 'warden.project.section', ['section' => 'http']],
        'logs'     => ['Logs', 'warden.project.section', ['section' => 'logs']],
        'mail'     => ['Mail & Notifs', 'warden.project.section', ['section' => 'mail']],
        'host'     => ['Host', 'warden.project.section', ['section' => 'host']],
        'security' => ['Security', 'warden.project.section', ['section' => 'security']],
        'delivery' => ['Delivery', 'warden.project.section', ['section' => 'delivery']],
        'uptime'   => ['Uptime', 'warden.project.section', ['section' => 'uptime']],
    ];
@endphp
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if($refresh > 0 && ($autoRefresh ?? true))
        <meta http-equiv="refresh" content="{{ $refresh }}">
    @endif
    <title>@yield('title', 'Warden') · Warden</title>
    @include('warden::partials.stylesheet')
    <style>
        ::-webkit-scrollbar{width:10px;height:10px}
        ::-webkit-scrollbar-track{background:transparent}
        ::-webkit-scrollbar-thumb{background:#2b3243;border-radius:6px;border:2px solid #0c0f17}
        body{background:#080a0f}
        .glass{background:linear-gradient(180deg,rgba(22,27,39,.6),rgba(12,15,23,.6));backdrop-filter:blur(8px)}

        @include('warden::partials.supplemental-css')
    </style>
</head>
<body class="font-sans text-slate-300 antialiased">
<div class="flex min-h-screen">

    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-20 flex w-64 flex-col border-r border-ink-700 bg-ink-900">
        <a href="{{ route('warden.overview') }}" class="flex items-center gap-2.5 px-5 h-16 border-b border-ink-700">
            <span class="relative flex h-3 w-3">
                <span class="absolute inline-flex h-full w-full rounded-full bg-brand-500 opacity-60 animate-ping"></span>
                <span class="relative inline-flex h-3 w-3 rounded-full bg-brand-500"></span>
            </span>
            <span class="text-[15px] font-semibold tracking-tight text-white">Warden</span>
            <span class="ml-auto text-[10px] uppercase tracking-widest text-slate-600">APM</span>
        </a>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
            <a href="{{ route('warden.overview') }}"
               class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition
               {{ ! $activeProject ? 'bg-ink-700 text-white' : 'text-slate-400 hover:bg-ink-800 hover:text-white' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Overview
            </a>

            @can('manageWarden')
                <a href="{{ route('warden.admin.projects') }}"
                   class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-400 transition hover:bg-ink-800 hover:text-white">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    Manage projects
                </a>
                <a href="{{ route('warden.admin.maintenance') }}"
                   class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-400 transition hover:bg-ink-800 hover:text-white">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Maintenance
                </a>
                <a href="{{ route('warden.admin.settings') }}"
                   class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-400 transition hover:bg-ink-800 hover:text-white">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Alert settings
                </a>
            @endcan

            <p class="px-3 pt-4 pb-1 text-[10px] font-semibold uppercase tracking-widest text-slate-600">Projects</p>

            @forelse($navProjects as $p)
                @php $isActive = $activeProject && $activeProject->id === $p->id; @endphp
                <a href="{{ route('warden.project', $p->slug) }}"
                   class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition
                   {{ $isActive ? 'bg-ink-700 text-white font-medium' : 'text-slate-400 hover:bg-ink-800 hover:text-white' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $isActive ? 'bg-brand-400' : 'bg-slate-600' }}"></span>
                    <span class="truncate">{{ $p->name }}</span>
                </a>

                @if($isActive)
                    <div class="ml-3 my-1 border-l border-ink-700 pl-2 space-y-0.5">
                        @foreach($sub as $key => [$label, $routeName, $params])
                            <a href="{{ route($routeName, array_merge(['project' => $p->slug], $params, ['range' => $currentRange])) }}"
                               class="block rounded-md px-3 py-1.5 text-[13px] transition
                               {{ ($activeSection ?? 'overview') === $key ? 'text-brand-400 font-medium' : 'text-slate-500 hover:text-slate-200' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                        <div class="my-1 border-t border-ink-700/60"></div>
                        <a href="{{ route('warden.issues', $p->slug) }}"
                           class="flex items-center justify-between rounded-md px-3 py-1.5 text-[13px] transition
                           {{ ($activeSection ?? '') === 'issues' ? 'text-rose-400 font-medium' : 'text-slate-500 hover:text-slate-200' }}">
                            Issues
                        </a>
                        <a href="{{ route('warden.incidents', $p->slug) }}"
                           class="block rounded-md px-3 py-1.5 text-[13px] transition
                           {{ ($activeSection ?? '') === 'incidents' ? 'text-amber-400 font-medium' : 'text-slate-500 hover:text-slate-200' }}">
                            Incidents
                        </a>
                        <a href="{{ route('warden.traces', $p->slug) }}"
                           class="block rounded-md px-3 py-1.5 text-[13px] transition
                           {{ ($activeSection ?? '') === 'traces' ? 'text-brand-400 font-medium' : 'text-slate-500 hover:text-slate-200' }}">
                            Traces
                        </a>
                    </div>
                @endif
            @empty
                <p class="px-3 py-2 text-[13px] text-slate-600">No projects yet.</p>
            @endforelse
        </nav>

        @if(session('warden_auth'))
            <div class="border-t border-ink-700 px-5 py-3">
                <p class="text-[11px] text-slate-500">Signed in as {{ session('warden_auth_admin') ? 'Admin' : 'Viewer' }}</p>
                <div class="mt-1.5 flex items-center gap-3 text-[11px]">
                    @unless(session('warden_auth_admin'))
                        <a href="{{ route('warden.login') }}" class="text-amber-300 transition hover:text-amber-200">Sign in as admin</a>
                    @endunless
                    <form method="POST" action="{{ route('warden.logout') }}">
                        @csrf
                        <button type="submit" class="text-slate-400 transition hover:text-white">Sign out</button>
                    </form>
                </div>
            </div>
        @else
            <div class="border-t border-ink-700 px-5 py-3 text-[11px] text-slate-600">
                Self-hosted · zero deps
            </div>
        @endif
    </aside>

    {{-- Main --}}
    <div class="flex-1 pl-64">
        <header class="sticky top-0 z-10 flex h-16 items-center gap-4 border-b border-ink-700 glass px-7">
            <div class="min-w-0">
                <h1 class="truncate text-[15px] font-semibold text-white">@yield('heading', 'Overview')</h1>
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
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span> live
                    </span>
                @endif
            </div>
        </header>

        <main class="px-7 py-7">
            @if(session('warden_auth') && ! session('warden_auth_admin'))
                <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-700/50 bg-amber-900/20 px-4 py-2.5 text-sm text-amber-200">
                    <span>Read-only access — sign in as admin to manage projects, alerts and maintenance.</span>
                    <a href="{{ route('warden.login') }}"
                       class="shrink-0 rounded-md border border-amber-600/60 px-3 py-1 text-xs font-medium text-amber-100 transition hover:bg-amber-600/20">
                        Sign in as admin
                    </a>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
