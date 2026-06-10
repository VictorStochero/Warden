@extends('warden::layout', ['active' => 'incidents', 'showRanges' => false])
@php use VictorStochero\Warden\Dashboard\Format; @endphp

@section('title', 'Incidents')
@section('heading', $project->name . ' · Incidents')

@section('content')
    <p class="mb-5 text-sm text-slate-500">
        Incidents open automatically from <span class="text-slate-300">issues</span> (unhandled exceptions)
        and <span class="text-slate-300">heartbeats</span> (a scheduled task that stopped reporting). They
        resolve on their own when the underlying cause clears.
    </p>

    <div class="overflow-hidden rounded-xl border border-ink-700 bg-ink-850">
        @if($incidents->isEmpty())
            <p class="px-4 py-16 text-center text-sm text-slate-600">No incidents 🎉</p>
        @else
            <table class="w-full text-sm">
                <tbody>
                    @foreach($incidents as $inc)
                        <tr class="border-t border-ink-700/70 first:border-0 hover:bg-ink-800">
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
