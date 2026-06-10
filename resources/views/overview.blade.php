@extends('warden::layout', ['showRanges' => false])
@php use VictorStochero\Warden\Dashboard\Format; @endphp

@section('title', __('warden::nav.overview'))
@section('heading', __('warden::nav.overview'))
@section('subheading', __('warden::overview.subheading'))

@section('content')
    @php
        $o = $overview;
        $healthRing = ['green' => 'bg-emerald-500', 'yellow' => 'bg-amber-500', 'red' => 'bg-rose-500'];
        $healthText = [
            'green' => __('warden::common.health.green'),
            'yellow' => __('warden::common.health.yellow'),
            'red' => __('warden::common.health.red'),
        ];
    @endphp

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        @include('warden::partials.kpi', ['label' => __('warden::overview.kpi.projects'), 'value' => $o['projects']->count(), 'tone' => 'brand'])
        @include('warden::partials.kpi', ['label' => __('warden::overview.kpi.throughput'), 'value' => Format::num($o['throughput']), 'sub' => __('warden::overview.kpi.requests')])
        @include('warden::partials.kpi', ['label' => __('warden::overview.kpi.open_issues'), 'value' => Format::num($o['open_issues']), 'tone' => $o['open_issues'] ? 'rose' : 'emerald'])
        @include('warden::partials.kpi', ['label' => __('warden::overview.kpi.open_incidents'), 'value' => Format::num($o['open_incidents']), 'tone' => $o['open_incidents'] ? 'amber' : 'emerald'])
    </div>

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
        <h2 class="mt-9 mb-3 text-xs font-semibold uppercase tracking-widest text-slate-500">{{ __('warden::overview.projects_heading') }}</h2>
        <div class="rounded-xl border border-dashed border-ink-700 bg-ink-900 p-12 text-center">
            <p class="text-slate-400">{{ ($activeGroup || $activeTag) ? __('warden::overview.empty.no_match') : __('warden::overview.empty.none_registered') }}</p>
            @if($activeGroup || $activeTag)
                <p class="mt-1 text-sm text-slate-600"><a href="{{ route('warden.overview') }}" class="text-brand-400">{{ __('warden::overview.empty.clear_filters') }}</a></p>
            @else
                <p class="mt-1 text-sm text-slate-600">{!! __('warden::overview.empty.hint', ['ship' => '<code class="text-brand-400">warden:ship</code>']) !!}</p>
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
            <h2 class="mt-9 mb-3 text-xs font-semibold uppercase tracking-widest text-slate-500">{{ __('warden::overview.ungrouped') }}</h2>
            @include('warden::partials.overview-cards', ['projects' => $ungroupedProjects, 'healthRing' => $healthRing, 'healthText' => $healthText])
        @endif
    @endif
@endsection
