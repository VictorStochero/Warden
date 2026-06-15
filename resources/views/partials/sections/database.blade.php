<div class="space-y-8">
    {{-- ── Query health ──────────────────────────────────────────────────── --}}
    <section class="space-y-6">
        <h2 class="text-[11px] font-semibold uppercase tracking-widest text-slate-500">{{ __('warden::project.database.health.title') }}</h2>

        @if($health['sampled'] > 0)
        <p class="text-xs text-slate-500">
            {{ __('warden::project.database.health.sampled', ['count' => number_format($health['sampled'])]) }}
        </p>
        @endif

        @php
            $healthCategories = [
                'n_plus_one'  => __('warden::project.database.health.n_plus_one'),
                'duplicates'  => __('warden::project.database.health.duplicates'),
                'select_star' => __('warden::project.database.health.select_star'),
                'no_where'    => __('warden::project.database.health.no_where'),
                'fat_requests'=> __('warden::project.database.health.fat_request'),
                'slow'        => __('warden::project.database.health.slow'),
            ];
            $anyFindings = collect($health['findings'])->contains(fn ($v) => ! empty($v));
        @endphp

        @if(!$anyFindings)
            <p class="text-sm text-slate-500">{{ __('warden::project.database.health.empty') }}</p>
        @else
            @foreach($healthCategories as $catKey => $catLabel)
                @if(!empty($health['findings'][$catKey]))
                    @include('warden::partials.card-open', ['title' => $catLabel, 'action' => null])
                        <ul class="divide-y divide-ink-700/50">
                            @foreach($health['findings'][$catKey] as $f)
                                <li class="flex flex-col gap-1 px-5 py-3 text-xs">
                                    @if(!empty($f['sql']))
                                        <span class="font-mono text-slate-300 break-all">{{ $f['sql'] }}</span>
                                    @endif
                                    <span class="flex flex-wrap items-center gap-3 text-slate-500">
                                        @if(!empty($f['count']))
                                            <span>× {{ $f['count'] }}</span>
                                        @endif
                                        @if(!empty($f['duration_us']))
                                            <span>{{ number_format($f['duration_us'] / 1000, 1) }} ms</span>
                                        @endif
                                        @if(!empty($f['trace_id']))
                                            <a href="{{ route('warden.trace', ['project' => $project->slug, 'traceId' => $f['trace_id']]) }}"
                                               class="font-mono text-brand-400 hover:text-brand-300 transition">
                                                {{ $f['trace_id'] }}
                                            </a>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @include('warden::partials.card-close')
                @endif
            @endforeach
        @endif
    </section>

    {{-- ── Queries ───────────────────────────────────────────────────────── --}}
    <section class="space-y-6">
        <h2 class="text-[11px] font-semibold uppercase tracking-widest text-slate-500">{{ __('warden::project.database.queries_heading') }}</h2>
        @include('warden::partials.card-open', ['title' => __('warden::project.queries.slowest_title'), 'action' => null])
            @include('warden::partials.query-table', ['queries' => $slow])
        @include('warden::partials.card-close')

        @include('warden::partials.card-open', ['title' => __('warden::project.queries.expensive_title'), 'action' => null])
            @include('warden::partials.query-table', ['queries' => $frequent])
        @include('warden::partials.card-close')
    </section>

    <section class="space-y-6">
        <h2 class="text-[11px] font-semibold uppercase tracking-widest text-slate-500">{{ __('warden::project.database.cache_heading') }}</h2>
        @include('warden::partials.card-open', ['title' => __('warden::project.cache.title'), 'action' => null])
            @include('warden::partials.cache-table', ['stores' => $stores])
        @include('warden::partials.card-close')
    </section>
</div>
