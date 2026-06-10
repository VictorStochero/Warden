@extends('warden::layout', ['active' => 'incidents', 'showRanges' => false])
@php use VictorStochero\Warden\Dashboard\Format; @endphp

@section('title', __('warden::incidents.list.title'))
@section('heading', $project->name . ' · ' . __('warden::incidents.list.heading'))

@section('content')
    <p class="mb-5 text-sm text-slate-500">
        {!! __('warden::incidents.list.description', [
            'issues'     => '<span class="text-slate-300">' . __('warden::incidents.list.description_issues') . '</span>',
            'heartbeats' => '<span class="text-slate-300">' . __('warden::incidents.list.description_heartbeats') . '</span>',
        ]) !!}
    </p>

    <div class="overflow-x-auto rounded-2xl border border-ink-700/70">
        @if($incidents->isEmpty())
            <p class="px-4 py-10 text-center text-sm text-slate-600">{{ __('warden::incidents.list.empty') }}</p>
        @else
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-ink-700/70">
                    @foreach($incidents as $inc)
                        <tr class="transition hover:bg-ink-850/50">
                            <td class="py-3 pl-4 pr-2 align-top">
                                <span class="rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider
                                    {{ $inc->severity === 'critical' ? 'bg-rose-500/10 text-rose-400' : 'bg-amber-500/10 text-amber-400' }}">
                                    {{ $inc->severity }}
                                </span>
                            </td>
                            <td class="px-2 py-3">
                                <a href="{{ route('warden.incident', [$project->slug, $inc->id]) }}" class="block">
                                    <span class="block truncate text-slate-200 hover:text-brand-400">{{ $inc->summary ?? $inc->subject }}</span>
                                    <span class="block truncate font-mono text-[11px] text-slate-500">{{ $inc->subject }}</span>
                                </a>
                            </td>
                            <td class="px-2 py-3 text-right align-top">
                                <span class="rounded px-1.5 py-0.5 text-[10px] font-medium {{ $inc->status === 'open' ? 'bg-rose-500/10 text-rose-400' : 'bg-emerald-500/10 text-emerald-400' }}">{{ $inc->status }}</span>
                            </td>
                            <td class="py-3 pl-2 pr-4 text-right align-top text-[11px] text-slate-500">{{ Format::ago($inc->started_at) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
