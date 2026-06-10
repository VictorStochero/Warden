@extends('warden::layout', ['showRanges' => false])
@php use VictorStochero\Warden\Dashboard\Format; @endphp

@section('title', 'Overview')
@section('heading', 'Overview')
@section('subheading', 'Fleet health across all observed projects')

@section('content')
    @php
        $o = $overview;
        $healthRing = ['green' => 'bg-emerald-500', 'yellow' => 'bg-amber-500', 'red' => 'bg-rose-500'];
        $healthText = ['green' => 'Healthy', 'yellow' => 'Degraded', 'red' => 'Down / errors'];
        // Child projects = everything except the parent's own self-monitor. With
        // none of them yet, the operator has only just installed — guide them.
        $childProjects = $o['projects']->reject(fn ($p) => $p->slug === ($selfSlug ?? 'parent'));
        $noChildrenYet = $childProjects->isEmpty() && ! ($activeGroup ?? null) && ! ($activeTag ?? null);
    @endphp

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        @include('warden::partials.kpi', ['label' => 'Projects', 'value' => $o['projects']->count(), 'tone' => 'brand'])
        @include('warden::partials.kpi', ['label' => 'Throughput · 5m', 'value' => Format::num($o['throughput']), 'sub' => 'requests'])
        @include('warden::partials.kpi', ['label' => 'Open issues', 'value' => Format::num($o['open_issues']), 'tone' => $o['open_issues'] ? 'rose' : 'emerald'])
        @include('warden::partials.kpi', ['label' => 'Open incidents', 'value' => Format::num($o['open_incidents']), 'tone' => $o['open_incidents'] ? 'amber' : 'emerald'])
    </div>

    @if($noChildrenYet)
        <div class="mt-9 rounded-xl border border-brand-600/40 bg-brand-600/5 p-6">
            <h2 class="text-sm font-semibold text-white">Getting started</h2>
            <p class="mt-1 text-sm text-slate-400">No observed apps yet — only this parent is monitoring itself. Add a project to start watching another app.</p>
            <ol class="mt-4 space-y-2 text-sm text-slate-300">
                <li><span class="mr-2 text-brand-400">1.</span>Create a project here — Warden mints its token + secret.</li>
                <li><span class="mr-2 text-brand-400">2.</span>On the app you want to watch, run the install command (or paste the <code class="text-brand-400">.env</code> keys) it gives you.</li>
                <li><span class="mr-2 text-brand-400">3.</span>Keep the scheduler cron running on both sides — <code class="text-brand-400">warden:ship</code> delivers batches to this parent every minute.</li>
            </ol>
            @can('manageWarden')
                <a href="{{ route('warden.admin.projects') }}"
                   class="mt-5 inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-500">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6"/></svg>
                    Add a project
                </a>
            @endcan
        </div>
    @endif

    @php
        $activeGroup = $activeGroup ?? null;
        $activeTag = $activeTag ?? null;
        $hasFilterOptions = $o['groups']->isNotEmpty() || $o['tags']->isNotEmpty();
    @endphp

    @if($hasFilterOptions)
        <div class="mt-9 space-y-3">
            @if($o['groups']->isNotEmpty())
                <div class="flex flex-wrap items-center gap-2">
                    <span class="w-12 text-[10px] font-semibold uppercase tracking-widest text-slate-600">Group</span>
                    <a href="{{ route('warden.overview', array_filter(['tag' => $activeTag])) }}"
                       class="rounded-full border px-3 py-1 text-xs transition {{ $activeGroup === null ? 'border-brand-500 bg-brand-600/15 text-brand-300' : 'border-ink-700 text-slate-400 hover:border-brand-500/50 hover:text-white' }}">All</a>
                    @foreach($o['groups'] as $g)
                        <a href="{{ route('warden.overview', array_filter(['group' => $g->slug, 'tag' => $activeTag])) }}"
                           class="rounded-full border px-3 py-1 text-xs transition {{ $activeGroup === $g->slug ? 'border-brand-500 bg-brand-600/15 text-brand-300' : 'border-ink-700 text-slate-400 hover:border-brand-500/50 hover:text-white' }}">{{ $g->name }}</a>
                    @endforeach
                </div>
            @endif
            @if($o['tags']->isNotEmpty())
                <div class="flex flex-wrap items-center gap-2">
                    <span class="w-12 text-[10px] font-semibold uppercase tracking-widest text-slate-600">Tag</span>
                    <a href="{{ route('warden.overview', array_filter(['group' => $activeGroup])) }}"
                       class="rounded-full border px-3 py-1 text-xs transition {{ $activeTag === null ? 'border-brand-500 bg-brand-600/15 text-brand-300' : 'border-ink-700 text-slate-400 hover:border-brand-500/50 hover:text-white' }}">All</a>
                    @foreach($o['tags'] as $t)
                        <a href="{{ route('warden.overview', array_filter(['tag' => $t->slug, 'group' => $activeGroup])) }}"
                           class="rounded-full border px-3 py-1 text-xs transition {{ $activeTag === $t->slug ? 'border-brand-500 bg-brand-600/15 text-brand-300' : 'border-ink-700 text-slate-400 hover:border-brand-500/50 hover:text-white' }}">{{ $t->name }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @if($o['projects']->isEmpty())
        <h2 class="mt-9 mb-3 text-xs font-semibold uppercase tracking-widest text-slate-500">Projects</h2>
        <div class="rounded-xl border border-dashed border-ink-700 bg-ink-900 p-12 text-center">
            <p class="text-slate-400">No projects {{ ($activeGroup || $activeTag) ? 'match this filter' : 'registered yet' }}.</p>
            @if($activeGroup || $activeTag)
                <p class="mt-1 text-sm text-slate-600"><a href="{{ route('warden.overview') }}" class="text-brand-400">Clear filters</a></p>
            @else
                <p class="mt-1 text-sm text-slate-600">Create a project, hand its token + secret to a child app, and run <code class="text-brand-400">warden:ship</code>.</p>
            @endif
        </div>
    @else
        @php
            // Cluster the (already filtered) projects by group name; ungrouped last.
            [$groupedProjects, $ungroupedProjects] = $o['projects']->partition(fn ($p) => ($p->group['name'] ?? null) !== null);
            $clusters = $groupedProjects->groupBy(fn ($p) => $p->group['name'])->sortKeys();
        @endphp

        @foreach($clusters as $groupName => $groupProjects)
            <h2 class="mt-9 mb-3 text-xs font-semibold uppercase tracking-widest text-slate-400">{{ $groupName }}</h2>
            @include('warden::partials.overview-cards', ['projects' => $groupProjects, 'healthRing' => $healthRing, 'healthText' => $healthText])
        @endforeach

        @if($ungroupedProjects->isNotEmpty())
            <h2 class="mt-9 mb-3 text-xs font-semibold uppercase tracking-widest text-slate-500">Ungrouped</h2>
            @include('warden::partials.overview-cards', ['projects' => $ungroupedProjects, 'healthRing' => $healthRing, 'healthText' => $healthText])
        @endif
    @endif
@endsection
