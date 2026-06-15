@extends('warden::layout')
@php use VictorStochero\Warden\Dashboard\Format; @endphp

@section('title', __('warden::admin.audit.title'))
@section('heading', __('warden::admin.audit.heading'))
@section('subheading', __('warden::admin.audit.subheading'))

@section('content')
    <div class="overflow-hidden rounded-2xl border border-ink-700/70 bg-ink-900 shadow-lg shadow-black/10">
        @if($entries->isEmpty())
            <p class="px-4 py-10 text-center text-sm text-slate-500">{{ __('warden::admin.audit.empty') }}</p>
        @else
            <table class="w-full text-left text-[13px]">
                <thead class="border-b border-ink-700/70 text-[10px] uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">{{ __('warden::admin.audit.col_when') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('warden::admin.audit.col_actor') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('warden::admin.audit.col_action') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('warden::admin.audit.col_target') }}</th>
                        <th class="px-4 py-2.5 font-semibold">{{ __('warden::admin.audit.col_ip') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-700/60">
                    @foreach($entries as $e)
                        <tr class="transition hover:bg-ink-850/50">
                            <td class="px-4 py-2.5 font-mono text-[12px] text-slate-400">{{ Format::at($e->created_at, 'Y-m-d H:i:s') }}</td>
                            <td class="px-4 py-2.5 text-slate-200">{{ $e->actor }}</td>
                            <td class="px-4 py-2.5"><span class="rounded bg-ink-800 px-1.5 py-0.5 text-[11px] text-slate-400">{{ $e->method }}</span> <span class="font-mono text-[12px] text-slate-300">{{ $e->action }}</span></td>
                            <td class="px-4 py-2.5 font-mono text-[12px] text-slate-500">{{ $e->target }}</td>
                            <td class="px-4 py-2.5 font-mono text-[12px] text-slate-500">{{ $e->ip }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
