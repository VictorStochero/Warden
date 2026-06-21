@php use VictorStochero\Warden\Dashboard\Format; @endphp
<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
    @foreach($projects as $p)
        <a href="{{ route('warden.project', $p->slug) }}"
           class="group rounded-2xl border border-ink-700/70 bg-ink-900 p-5 shadow-lg shadow-black/10 transition hover:border-brand-500/40 hover:shadow-brand-500/[0.07]">
            <div class="flex items-center gap-2.5">
                <span class="h-2.5 w-2.5 rounded-full {{ $healthRing[$p->health] ?? 'bg-slate-600' }}"></span>
                <span class="font-medium text-white group-hover:text-brand-400">{{ $p->name }}</span>
                @if(($captureFlags[$p->slug] ?? false))
                    <span class="rounded-full border border-ink-600 px-1.5 py-0.5 text-[9px] uppercase tracking-wide text-slate-400" title="{{ __('warden::capture.reduced_title') }}">{{ __('warden::capture.badge') }}</span>
                @endif
                <span class="ml-auto font-mono text-[11px] text-slate-500">{{ Format::ago($p->last_seen_at) }}</span>
            </div>
            <p class="mt-0.5 pl-5 text-[11px] uppercase tracking-wider text-slate-600">
                {{ $healthText[$p->health] ?? 'unknown' }}
                @isset($p->uptime)<span class="text-slate-500">· {{ $p->uptime }}{{ __('warden::project.overview_cards.uptime_30d') }}</span>@endisset
            </p>

            @if(! empty($p->tags))
                <div class="mt-2 flex flex-wrap gap-1.5 pl-5">
                    @foreach($p->tags as $tag)
                        <span class="rounded-full border border-ink-700 bg-ink-900 px-2 py-0.5 text-[10px] text-slate-400">{{ $tag['name'] }}</span>
                    @endforeach
                </div>
            @endif

            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl bg-ink-850 py-2.5 ring-1 ring-inset ring-ink-700/50">
                    <p class="font-mono text-lg font-semibold text-white">{{ Format::num($p->throughput) }}</p>
                    <p class="wdn-eyebrow text-[9px] text-slate-500">{{ __('warden::project.overview_cards.req_5m') }}</p>
                </div>
                <div class="rounded-xl bg-ink-850 py-2.5 ring-1 ring-inset ring-ink-700/50">
                    <p class="font-mono text-lg font-semibold {{ $p->error_rate >= 5 ? 'text-rose-400' : ($p->error_rate >= 1 ? 'text-amber-400' : 'text-emerald-400') }}">{{ $p->error_rate }}%</p>
                    <p class="wdn-eyebrow text-[9px] text-slate-500">{{ __('warden::project.overview_cards.errors') }}</p>
                </div>
                <div class="rounded-xl bg-ink-850 py-2.5 ring-1 ring-inset ring-ink-700/50">
                    <p class="font-mono text-lg font-semibold text-white">{{ $p->p95_ms !== null ? Format::ms($p->p95_ms) : '—' }}</p>
                    <p class="wdn-eyebrow text-[9px] text-slate-500">p95</p>
                </div>
            </div>
        </a>
    @endforeach
</div>
