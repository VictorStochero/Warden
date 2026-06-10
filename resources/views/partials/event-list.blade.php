@php
    use VictorStochero\Warden\Dashboard\Format;
    use Illuminate\Support\Str;
    $title = $title ?? 'Recent events';
@endphp
@include('warden::partials.card-open', ['title' => $title, 'action' => null])
    @if($events->isEmpty())
        <p class="px-4 py-6 text-center text-sm text-slate-600">Nothing captured yet — run some traffic (or <span class="font-mono text-slate-500">warden:demo</span> on the child).</p>
    @else
        <div class="divide-y divide-ink-700/70">
            @foreach($events as $e)
                @php $p = is_array($e->payload) ? $e->payload : []; @endphp
                <a href="{{ $e->trace_id ? route('warden.trace', [$project->slug, $e->trace_id]) : '#' }}"
                   class="flex items-start gap-3 px-4 py-2.5 text-sm transition hover:bg-ink-800">
                    <span class="w-24 shrink-0 pt-0.5 text-[11px] text-slate-500">{{ Format::ago($e->occurred_at) }}</span>
                    <div class="min-w-0 flex-1">
                        @switch($type)
                            @case('log')
                                @php $tone = in_array($p['level'] ?? '', ['emergency','alert','critical','error'], true) ? 'rose' : (($p['level'] ?? '') === 'warning' ? 'amber' : 'sky'); @endphp
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-{{ $tone }}-400">{{ $p['level'] ?? 'log' }}</span>
                                    <span class="truncate text-slate-200">{{ Str::limit((string) ($p['message'] ?? ''), 160) }}</span>
                                </div>
                                @if(! empty($p['context']))
                                    <p class="mt-0.5 truncate font-mono text-[11px] text-slate-500">{{ Str::limit((string) json_encode($p['context']), 140) }}</p>
                                @endif
                                @break

                            @case('mail')
                                <div class="truncate text-slate-200">{{ $p['subject'] ?? '(no subject)' }}</div>
                                <p class="mt-0.5 truncate text-[11px] text-slate-500">
                                    to {{ implode(', ', (array) ($p['to'] ?? [])) ?: '—' }}
                                    <span class="text-slate-600">· {{ $p['mailer'] ?? 'default' }}</span>
                                </p>
                                @break

                            @case('notification')
                                <div class="truncate text-slate-200">{{ class_basename($p['type'] ?? 'Notification') }}</div>
                                <p class="mt-0.5 truncate text-[11px] text-slate-500">
                                    via {{ $p['channel'] ?? '?' }}
                                    @if(! empty($p['notifiable'])) <span class="text-slate-600">· {{ class_basename($p['notifiable']) }}</span> @endif
                                </p>
                                @break

                            @case('http')
                                @php $st = (int) ($p['status'] ?? 0); $tone = ($st === 0 || $st >= 500) ? 'rose' : ($st >= 400 ? 'amber' : 'emerald'); @endphp
                                <div class="flex items-center gap-2">
                                    <span class="rounded bg-ink-700 px-1.5 py-0.5 text-[10px] font-medium text-slate-300">{{ $p['method'] ?? 'GET' }}</span>
                                    <span class="text-[11px] font-semibold text-{{ $tone }}-400">{{ $st ?: 'ERR' }}</span>
                                    <span class="truncate text-slate-300">{{ $p['url'] ?? $p['host'] ?? '' }}</span>
                                </div>
                                @break

                            @case('job')
                                @php $s = $p['status'] ?? ''; $tone = $s === 'failed' ? 'rose' : ($s === 'processed' ? 'emerald' : 'slate'); @endphp
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-{{ $tone }}-400">{{ $s ?: 'job' }}</span>
                                    <span class="truncate text-slate-200">{{ class_basename($p['class'] ?? 'Job') }}</span>
                                </div>
                                <p class="mt-0.5 truncate text-[11px] text-slate-500">
                                    {{ $p['connection'] ?? '' }}@if(! empty($p['queue'])) · {{ $p['queue'] }}@endif
                                    @if(! empty($p['error'])) <span class="text-rose-400">· {{ Str::limit((string) $p['error'], 80) }}</span>@endif
                                </p>
                                @break

                            @case('schedule')
                                @php $s = $p['status'] ?? ''; $tone = $s === 'failed' ? 'rose' : ($s === 'skipped' ? 'amber' : 'emerald'); @endphp
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-{{ $tone }}-400">{{ $s ?: 'run' }}</span>
                                    <span class="truncate text-slate-200">{{ $p['task'] ?? 'task' }}</span>
                                </div>
                                <p class="mt-0.5 truncate font-mono text-[11px] text-slate-500">
                                    {{ $p['expression'] ?? '' }}
                                    @if(! empty($p['error'])) <span class="text-rose-400">· {{ Str::limit((string) $p['error'], 80) }}</span>@endif
                                </p>
                                @break

                            @case('request')
                                @php $st = (int) ($p['status'] ?? 0); $tone = $st >= 500 ? 'rose' : ($st >= 400 ? 'amber' : 'emerald'); @endphp
                                <div class="flex items-center gap-2">
                                    <span class="rounded bg-ink-700 px-1.5 py-0.5 text-[10px] font-medium text-slate-300">{{ $p['method'] ?? 'GET' }}</span>
                                    <span class="text-[11px] font-semibold text-{{ $tone }}-400">{{ $st ?: '—' }}</span>
                                    <span class="truncate text-slate-300">{{ $p['route'] ?? $p['path'] ?? '/' }}</span>
                                </div>
                                @break

                            @default
                                <span class="truncate text-slate-300">{{ Str::limit((string) json_encode($p), 160) }}</span>
                        @endswitch
                    </div>
                    @if($e->duration_us !== null)
                        <span class="shrink-0 pt-0.5 font-mono text-[11px] text-slate-500">{{ Format::dur($e->duration_us) }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
@include('warden::partials.card-close')
