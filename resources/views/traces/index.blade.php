@extends('warden::layout', ['active' => 'traces', 'showRanges' => false])
@php use VictorStochero\Warden\Dashboard\Format; @endphp

@section('title', __('warden::traces.page_title'))
@section('heading', __('warden::traces.heading', ['name' => $project->name]))

@section('content')
    <p class="mb-5 text-sm text-slate-500">
        {!! __('warden::traces.intro') !!}
    </p>

    @php $typeColor = ['request' => 'text-brand-400', 'command' => 'text-sky-400', 'schedule' => 'text-violet-400', 'job' => 'text-emerald-400']; @endphp
    <div class="overflow-x-auto rounded-2xl border border-ink-700/70">
        @if($traces->isEmpty())
            <p class="px-4 py-10 text-center text-sm text-slate-600">{{ __('warden::traces.list.empty') }}</p>
        @else
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-ink-700/70">
                    @foreach($traces as $t)
                        <tr class="transition hover:bg-ink-850/50">
                            <td class="py-3 pl-4 pr-2 w-20"><span class="text-[10px] font-semibold uppercase tracking-wider {{ $typeColor[$t['type']] ?? 'text-slate-500' }}">{{ $t['type'] }}</span></td>
                            <td class="px-2 py-3">
                                <a href="{{ route('warden.trace', [$project->slug, $t['trace_id']]) }}" class="block truncate {{ $t['errored'] ? 'text-rose-400' : 'text-slate-200' }} hover:text-brand-400">{{ $t['label'] }}</a>
                            </td>
                            <td class="px-2 py-3 text-right">
                                @if($t['errored'])<span class="rounded bg-rose-500/10 px-1.5 py-0.5 text-[10px] font-medium text-rose-400">{{ __('warden::traces.badge.error') }}</span>@endif
                            </td>
                            <td class="px-2 py-3 text-right font-mono text-[12px] text-slate-400">{{ Format::dur($t['duration_us']) }}</td>
                            <td class="py-3 pl-2 pr-4 text-right text-[11px] text-slate-500">{{ Format::ago($t['occurred_at']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
