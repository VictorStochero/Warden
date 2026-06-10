@extends('warden::layout', ['active' => 'issues', 'showRanges' => false])
@php use VictorStochero\Warden\Dashboard\Format; @endphp

@section('title', 'Issues')
@section('heading', $project->name . ' · Issues')

@section('content')
    <p class="mb-5 text-sm text-slate-500">
        Issues are <span class="text-slate-300">unhandled exceptions</span> grouped by fingerprint. An empty list
        means none were reported in this window — that's healthy, not a misconfiguration.
    </p>

    @php $tabs = ['open' => 'Open', 'resolved' => 'Resolved', 'ignored' => 'Ignored']; @endphp
    <div class="mb-5 flex items-center gap-1 rounded-lg border border-ink-700 bg-ink-850 p-1 w-max">
        @foreach($tabs as $key => $label)
            <a href="{{ route('warden.issues', [$project->slug, 'status' => $key]) }}"
               class="rounded-md px-3 py-1.5 text-sm font-medium transition {{ $status === $key ? 'bg-ink-700 text-white' : 'text-slate-400 hover:text-white' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-xl border border-ink-700 bg-ink-850">
        @if($issues->isEmpty())
            <p class="px-4 py-16 text-center text-sm text-slate-600">No {{ $status }} issues</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-ink-700 text-[11px] uppercase tracking-wider text-slate-500">
                        <th class="px-4 py-2.5 text-left font-medium">Issue</th>
                        <th class="px-4 py-2.5 text-right font-medium">Events</th>
                        <th class="px-4 py-2.5 text-right font-medium">Users</th>
                        <th class="px-4 py-2.5 text-right font-medium">Last seen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($issues as $issue)
                        <tr class="border-t border-ink-700/70 hover:bg-ink-800">
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
