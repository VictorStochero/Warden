@php
    use VictorStochero\Warden\Dashboard\Format;
    use Illuminate\Support\Str;
    $title = $title ?? __('warden::project.event_list.default_title');
@endphp
@include('warden::partials.card-open', ['title' => $title, 'action' => null])
    @if($events->isEmpty())
        <p class="px-4 py-8 text-center text-sm text-slate-600">{!! __('warden::project.event_list.empty') !!}</p>
    @else
        <div class="divide-y divide-ink-700/70">
            @foreach($events as $e)
                @php $p = is_array($e->payload) ? $e->payload : []; @endphp
                <a href="{{ isset($e->id) ? route('warden.event', [$project->slug, $e->id]) : ($e->trace_id ? route('warden.trace', [$project->slug, $e->trace_id]) : '#') }}"
                   class="flex items-start gap-3 px-4 py-2.5 text-sm transition hover:bg-ink-850/50">
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
                                <div class="truncate text-slate-200">{{ $p['subject'] ?? __('warden::project.event_list.no_subject') }}</div>
                                <p class="mt-0.5 truncate text-[11px] text-slate-500">
                                    {{ __('warden::project.event_list.to') }} {{ implode(', ', (array) ($p['to'] ?? [])) ?: '—' }}
                                    <span class="text-slate-600">· {{ $p['mailer'] ?? 'default' }}</span>
                                </p>
                                @break

                            @case('notification')
                                <div class="truncate text-slate-200">{{ class_basename($p['type'] ?? 'Notification') }}</div>
                                <p class="mt-0.5 truncate text-[11px] text-slate-500">
                                    {{ __('warden::project.event_list.via') }} {{ $p['channel'] ?? '?' }}
                                    @if(! empty($p['notifiable'])) <span class="text-slate-600">· {{ class_basename($p['notifiable']) }}</span> @endif
                                </p>
                                @break

                            @case('http')
                                @php $st = (int) ($p['status'] ?? 0); $tone = ($st === 0 || $st >= 500) ? 'rose' : ($st >= 400 ? 'amber' : 'emerald'); @endphp
                                <div class="flex items-center gap-2">
                                    <span class="rounded-md bg-ink-850 px-1.5 py-0.5 text-[10px] font-medium text-slate-300 ring-1 ring-inset ring-ink-700/50">{{ $p['method'] ?? 'GET' }}</span>
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
                                    <span class="rounded-md bg-ink-850 px-1.5 py-0.5 text-[10px] font-medium text-slate-300 ring-1 ring-inset ring-ink-700/50">{{ $p['method'] ?? 'GET' }}</span>
                                    <span class="text-[11px] font-semibold text-{{ $tone }}-400">{{ $st ?: '—' }}</span>
                                    <span class="truncate text-slate-300">{{ $p['route'] ?? $p['path'] ?? '/' }}</span>
                                </div>
                                @break

                            @case('exception')
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-[12px] font-semibold text-rose-400">{{ class_basename($p['class'] ?? 'Exception') }}</span>
                                    @if(! empty($p['method']) || ! empty($p['path']))
                                        <span class="rounded-md bg-ink-850 px-1.5 py-0.5 text-[10px] text-slate-400 ring-1 ring-inset ring-ink-700/50">{{ $p['method'] ?? '' }} {{ $p['route'] ?? $p['path'] ?? '' }}</span>
                                    @endif
                                </div>
                                <p class="mt-0.5 truncate text-[11px] text-slate-500">{{ Str::limit((string) ($p['message'] ?? ''), 140) }}</p>
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
