@extends('warden::layout', ['showRanges' => false])
@php
    use VictorStochero\Warden\Dashboard\Format;
    use Illuminate\Support\Str;
@endphp

@section('title', __('warden::events.title'))
@section('heading', __('warden::events.title'))
@section('subheading', ucfirst($event->type))

@section('content')
    @php
        $p = is_array($event->payload) ? $event->payload : [];
        $tones = ['exception' => 'rose', 'log' => 'sky', 'mail' => 'emerald', 'notification' => 'emerald', 'job' => 'amber', 'schedule' => 'amber', 'query' => 'sky', 'request' => 'brand', 'http' => 'brand', 'cache' => 'sky'];
        $tone = $tones[$event->type] ?? 'brand';
        // Render a payload value as a human string for the definition rows.
        $val = function ($v) {
            if (is_array($v)) {
                return $v === [] ? null : (array_is_list($v) ? implode(', ', array_map(fn ($x) => is_scalar($x) ? (string) $x : json_encode($x), $v)) : json_encode($v));
            }
            if (is_bool($v)) {
                return $v ? 'true' : 'false';
            }

            return ($v === null || $v === '') ? null : (string) $v;
        };
        // Build the labelled rows shown for this event type (additive — nothing removed).
        $rows = [];
        if ($event->type === 'mail') {
            $rows = [
                'events.mail_subject' => $val($p['subject'] ?? null),
                'events.mail_from' => $val($p['from'] ?? null),
                'events.mail_to' => $val($p['to'] ?? null),
                'events.mail_cc' => $val($p['cc'] ?? null),
                'events.mail_bcc' => $val($p['bcc'] ?? null),
                'events.mail_reply_to' => $val($p['reply_to'] ?? null),
                'events.mail_mailer' => $val($p['mailer'] ?? null),
            ];
        } elseif ($event->type === 'exception') {
            $rows = [
                'events.exc_location' => trim(($p['file'] ?? '?').':'.($p['line'] ?? '?'), ':'),
                'events.exc_route' => $val($p['route'] ?? null),
                'events.exc_method' => $val($p['method'] ?? null),
                'events.exc_path' => $val($p['path'] ?? null),
                'events.exc_user' => $val($p['user_id'] ?? null),
                'events.exc_code' => $val($p['code'] ?? null),
            ];
        } elseif ($event->type === 'query') {
            $rows = ['events.query_connection' => $val($p['connection'] ?? null)];
        } elseif ($event->type === 'log') {
            $rows = ['events.log_level' => $val($p['level'] ?? null)];
        }
        $rows = array_filter($rows, fn ($v) => $v !== null && $v !== '');
    @endphp

    <div class="mb-5">
        <a href="{{ url()->previous() }}" class="text-[13px] text-brand-400 transition hover:text-brand-300">← {{ __('warden::issues.show.back') }}</a>
    </div>

    {{-- Header --}}
    <div class="rounded-2xl border border-ink-700/70 bg-ink-900 p-5 shadow-lg shadow-black/10">
        <div class="flex flex-wrap items-center gap-3">
            <span class="wdn-eyebrow rounded-lg bg-{{ $tone }}-500/10 px-2.5 py-1 text-[11px] text-{{ $tone }}-400 ring-1 ring-inset ring-{{ $tone }}-500/20">{{ $event->type }}</span>
            @if(isset($p['class']))
                <span class="font-mono text-[15px] text-white">{{ $p['class'] }}</span>
            @elseif(isset($p['subject']))
                <span class="text-[15px] text-white">{{ $p['subject'] }}</span>
            @endif
        </div>

        <dl class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <dt class="wdn-eyebrow text-[10px] text-slate-500">{{ __('warden::events.when') }}</dt>
                <dd class="mt-0.5 font-mono text-[13px] text-slate-300">{{ Format::at($event->occurred_at, 'Y-m-d H:i:s') }}</dd>
            </div>
            @if($event->duration_us !== null)
                <div>
                    <dt class="wdn-eyebrow text-[10px] text-slate-500">{{ __('warden::events.duration') }}</dt>
                    <dd class="mt-0.5 font-mono text-[13px] text-slate-300">{{ Format::dur($event->duration_us) }}</dd>
                </div>
            @endif
            @if($event->trace_id)
                <div class="min-w-0">
                    <dt class="wdn-eyebrow text-[10px] text-slate-500">{{ __('warden::events.trace') }}</dt>
                    <dd class="mt-0.5 truncate font-mono text-[13px] text-slate-300">{{ Str::limit($event->trace_id, 16, '') }}</dd>
                </div>
            @endif
            @if($event->span_id)
                <div class="min-w-0">
                    <dt class="wdn-eyebrow text-[10px] text-slate-500">{{ __('warden::events.span') }}</dt>
                    <dd class="mt-0.5 truncate font-mono text-[13px] text-slate-300">{{ Str::limit((string) $event->span_id, 16, '') }}</dd>
                </div>
            @endif
        </dl>

        @if($event->trace_id || ($issue ?? null))
            <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 border-t border-ink-700/70 pt-4">
                @if($event->trace_id)
                    <a href="{{ route('warden.trace', [$project->slug, $event->trace_id]) }}"
                       class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-400 transition hover:text-brand-300">
                        {{ __('warden::events.view_trace') }} →
                    </a>
                @endif
                @if($issue ?? null)
                    <a href="{{ route('warden.issue', [$project->slug, $issue->id]) }}"
                       class="inline-flex items-center gap-1.5 text-sm font-medium text-rose-400 transition hover:text-rose-300">
                        {{ __('warden::events.view_issue') }}
                        <span class="rounded bg-rose-500/10 px-1.5 py-0.5 font-mono text-[11px] ring-1 ring-inset ring-rose-500/20">{{ Format::num($issue->count) }}×</span> →
                    </a>
                @endif
            </div>
        @endif
    </div>

    {{-- Per-type highlights --}}
    @if(! empty($rows) || isset($p['message']) || isset($p['sql']) || ! empty($p['context']) || ! empty($p['bindings']) || ! empty($p['stack']))
        <div class="mt-5 space-y-5">
            @if(isset($p['message']))
                <div class="rounded-2xl border border-ink-700/70 bg-ink-900 p-5 shadow-lg shadow-black/10">
                    <p class="wdn-eyebrow text-[10px] text-slate-500">{{ __($event->type === 'exception' ? 'warden::events.exc_message' : 'warden::events.log_message') }}</p>
                    <p class="mt-1.5 whitespace-pre-wrap break-words text-sm text-slate-200">{{ $p['message'] }}</p>
                </div>
            @endif

            @if(! empty($rows))
                <div class="overflow-hidden rounded-2xl border border-ink-700/70 bg-ink-900 shadow-lg shadow-black/10">
                    <dl class="divide-y divide-ink-700/60">
                        @foreach($rows as $key => $value)
                            <div class="flex flex-wrap gap-2 px-5 py-3">
                                <dt class="w-32 shrink-0 wdn-eyebrow text-[10px] text-slate-500">{{ __('warden::'.$key) }}</dt>
                                <dd class="min-w-0 flex-1 break-words font-mono text-[13px] text-slate-200">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif

            @if(isset($p['sql']))
                <div class="rounded-2xl border border-ink-700/70 bg-ink-900 p-5 shadow-lg shadow-black/10">
                    <p class="wdn-eyebrow text-[10px] text-slate-500">{{ __('warden::events.query_sql') }}</p>
                    <pre class="mt-2 max-h-80 overflow-auto whitespace-pre-wrap break-words rounded-xl bg-ink-950 p-3 font-mono text-[12px] text-emerald-300">{{ $p['sql'] }}</pre>
                    @if(! empty($p['bindings']))
                        <p class="mt-4 wdn-eyebrow text-[10px] text-slate-500">{{ __('warden::events.query_bindings') }}</p>
                        <pre class="mt-2 overflow-auto rounded-xl bg-ink-950 p-3 font-mono text-[12px] text-slate-300">{{ json_encode($p['bindings'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                    @endif
                </div>
            @endif

            @if(! empty($p['context']))
                <div class="rounded-2xl border border-ink-700/70 bg-ink-900 p-5 shadow-lg shadow-black/10">
                    <p class="wdn-eyebrow text-[10px] text-slate-500">{{ __('warden::events.log_context') }}</p>
                    <pre class="mt-2 max-h-80 overflow-auto rounded-xl bg-ink-950 p-3 font-mono text-[12px] text-slate-300">{{ json_encode($p['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            @endif

            @if(! empty($p['stack']) && is_array($p['stack']))
                <div class="overflow-hidden rounded-2xl border border-ink-700/70 bg-ink-900 shadow-lg shadow-black/10">
                    <div class="border-b border-ink-700/70 px-5 py-3.5"><h3 class="wdn-eyebrow text-[11px] text-slate-400">{{ __('warden::events.exc_stack') }}</h3></div>
                    <div class="divide-y divide-ink-700/60 font-mono text-[12px]">
                        @foreach($p['stack'] as $i => $frame)
                            <div class="flex gap-3 px-4 py-2 {{ $i === 0 ? 'bg-rose-500/5' : '' }}">
                                <span class="w-6 shrink-0 text-right text-slate-600">{{ $i }}</span>
                                <div class="min-w-0">
                                    @if(! empty($frame['class']) || ! empty($frame['function']))
                                        <p class="text-slate-200">{{ $frame['class'] ?? '' }}{{ ! empty($frame['class']) ? '::' : '' }}{{ $frame['function'] ?? '' }}()</p>
                                    @endif
                                    <p class="truncate text-slate-500">{{ $frame['file'] ?? '?' }}<span class="text-slate-600">:{{ $frame['line'] ?? '?' }}</span></p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Full payload — every captured field, for debugging & root-cause analysis --}}
    <div class="mt-5 overflow-hidden rounded-2xl border border-ink-700/70 bg-ink-900 shadow-lg shadow-black/10">
        <div class="border-b border-ink-700/70 px-5 py-3.5">
            <h3 class="wdn-eyebrow text-[11px] text-slate-400">{{ __('warden::events.raw') }}</h3>
            <p class="mt-1 text-xs text-slate-500">{{ __('warden::events.raw_hint') }}</p>
        </div>
        <pre class="max-h-[28rem] overflow-auto p-5 font-mono text-[12px] leading-relaxed text-slate-300">{{ json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>
@endsection
