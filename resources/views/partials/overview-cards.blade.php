@php use VictorStochero\Warden\Dashboard\Format; @endphp
<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
    @foreach($projects as $p)
        <a href="{{ route('warden.project', $p->slug) }}"
           class="group rounded-xl border border-ink-700 bg-ink-850 p-5 transition hover:border-brand-500/50 hover:bg-ink-800">
            <div class="flex items-center gap-2.5">
                <span class="h-2.5 w-2.5 rounded-full {{ $healthRing[$p->health] ?? 'bg-slate-600' }}"></span>
                <span class="font-medium text-white group-hover:text-brand-400">{{ $p->name }}</span>
                <span class="ml-auto text-[11px] text-slate-500">{{ Format::ago($p->last_seen_at) }}</span>
            </div>
            <p class="mt-0.5 pl-5 text-[11px] uppercase tracking-wider text-slate-600">
                {{ $healthText[$p->health] ?? 'unknown' }}
                @isset($p->uptime)<span class="text-slate-500">· {{ $p->uptime }}% uptime · 30d</span>@endisset
            </p>

            @if(! empty($p->tags))
                <div class="mt-2 flex flex-wrap gap-1.5 pl-5">
                    @foreach($p->tags as $tag)
                        <span class="rounded-full border border-ink-700 bg-ink-900 px-2 py-0.5 text-[10px] text-slate-400">{{ $tag['name'] }}</span>
                    @endforeach
                </div>
            @endif

            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-lg bg-ink-900 py-2.5">
                    <p class="text-lg font-semibold text-white">{{ Format::num($p->throughput) }}</p>
                    <p class="text-[10px] uppercase tracking-wider text-slate-500">req · 5m</p>
                </div>
                <div class="rounded-lg bg-ink-900 py-2.5">
                    <p class="text-lg font-semibold {{ $p->error_rate >= 5 ? 'text-rose-400' : ($p->error_rate >= 1 ? 'text-amber-400' : 'text-emerald-400') }}">{{ $p->error_rate }}%</p>
                    <p class="text-[10px] uppercase tracking-wider text-slate-500">errors</p>
                </div>
                <div class="rounded-lg bg-ink-900 py-2.5">
                    <p class="text-lg font-semibold text-white">{{ $p->p95_ms !== null ? Format::ms($p->p95_ms) : '—' }}</p>
                    <p class="text-[10px] uppercase tracking-wider text-slate-500">p95</p>
                </div>
            </div>
        </a>
    @endforeach
</div>
