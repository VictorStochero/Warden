@php use VictorStochero\Warden\Dashboard\Format; use Illuminate\Support\Str; @endphp
<div class="grid gap-5 lg:grid-cols-2">
    @include('warden::partials.card-open', ['title' => 'Heartbeats', 'action' => null])
        @forelse($heartbeats as $hb)
            <div class="flex items-center gap-2.5 border-t border-ink-700 px-4 py-3 text-sm first:border-0">
                <span class="h-2 w-2 rounded-full {{ $hb['healthy'] ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                <span class="truncate text-slate-200">{{ Str::after($hb['key'], 'schedule:') }}</span>
                <span class="ml-auto text-[11px] text-slate-500">every {{ $hb['interval'] }}s · {{ Format::ago($hb['last_seen']) }}</span>
            </div>
        @empty
            <p class="px-4 py-6 text-center text-sm text-slate-600">No heartbeats tracked yet</p>
        @endforelse
    @include('warden::partials.card-close')

    @include('warden::partials.card-open', ['title' => 'Scheduled tasks', 'action' => null])
        @if($tasks->isEmpty())
            <p class="px-4 py-6 text-center text-sm text-slate-600">No task runs in range</p>
        @else
            <table class="w-full text-sm">
                <thead><tr class="text-[11px] uppercase tracking-wider text-slate-500">
                    <th class="px-4 py-2 text-left font-medium">Task</th>
                    <th class="px-4 py-2 text-right font-medium">Runs</th>
                    <th class="px-4 py-2 text-right font-medium">Avg</th>
                </tr></thead>
                <tbody>
                    @foreach($tasks as $t)
                        <tr class="border-t border-ink-700/70 hover:bg-ink-800">
                            <td class="px-4 py-2.5 font-mono text-[12px] text-slate-200">{{ $t['key'] }}</td>
                            <td class="px-4 py-2.5 text-right text-slate-300">{{ Format::num($t['count']) }}</td>
                            <td class="px-4 py-2.5 text-right text-slate-400">{{ Format::dur($t['avg']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @include('warden::partials.card-close')
</div>

<div class="mt-5">
    @include('warden::partials.event-list', ['events' => $recent, 'type' => 'schedule', 'title' => 'Recent task runs'])
</div>
