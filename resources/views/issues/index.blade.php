@extends('warden::layout', ['active' => 'issues', 'showRanges' => false])
@php use VictorStochero\Warden\Dashboard\Format; @endphp

@section('title', __('warden::issues.list.title'))
@section('heading', $project->name . ' · ' . __('warden::issues.list.heading'))

@section('content')
    <p class="mb-5 text-sm text-slate-500">
        {!! __('warden::issues.list.description', ['unhandled' => '<span class="text-slate-300">' . __('warden::issues.list.description_unhandled') . '</span>']) !!}
    </p>

    @php $tabs = ['open' => __('warden::issues.list.tab_open'), 'resolved' => __('warden::issues.list.tab_resolved'), 'ignored' => __('warden::issues.list.tab_ignored')]; @endphp
    <div class="mb-5 flex items-center gap-1 rounded-xl border border-ink-700/70 bg-ink-850 p-1 w-max">
        @foreach($tabs as $key => $label)
            <a href="{{ route('warden.issues', [$project->slug, 'status' => $key]) }}"
               class="rounded-md px-3 py-1.5 text-sm font-medium transition {{ $status === $key ? 'bg-ink-700 text-white' : 'text-slate-400 hover:text-white' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-2xl border border-ink-700/70">
        @if($issues->isEmpty())
            <p class="px-4 py-10 text-center text-sm text-slate-600">{{ __('warden::issues.list.empty', ['status' => $status]) }}</p>
        @else
            <table class="min-w-full text-sm">
                <thead class="bg-ink-850">
                    <tr class="border-b border-ink-700/70 text-[11px] uppercase tracking-wider text-slate-500">
                        <th class="px-4 py-3 text-left font-medium">{{ __('warden::issues.list.col_issue') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('warden::issues.list.col_events') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('warden::issues.list.col_users') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ __('warden::issues.list.col_last_seen') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-700/70">
                    @foreach($issues as $issue)
                        <tr class="transition hover:bg-ink-850/50">
                            <td class="px-4 py-3">
                                <a href="{{ route('warden.issue', [$project->slug, $issue->id]) }}" class="block">
                                    <div class="flex items-center gap-2">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $issue->status === 'open' ? 'bg-rose-500' : 'bg-slate-600' }}"></span>
                                        <span class="font-medium text-slate-100">{{ class_basename($issue->class) }}</span>
                                        @if($issue->priority)
                                            <span class="rounded bg-ink-700 px-1.5 py-0.5 text-[10px] uppercase tracking-wider text-slate-400">{{ $issue->priority }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-0.5 truncate text-[12px] text-slate-500 max-w-2xl">{{ $issue->message }}</p>
                                </a>
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-slate-200">{{ Format::num($issue->count) }}</td>
                            <td class="px-4 py-3 text-right text-slate-400">{{ Format::num($issue->users_affected) }}</td>
                            <td class="px-4 py-3 text-right text-slate-500">{{ Format::ago($issue->last_seen_at) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
