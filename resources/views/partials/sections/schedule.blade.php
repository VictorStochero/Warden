@php use VictorStochero\Warden\Dashboard\Format; use Illuminate\Support\Str; @endphp
<div class="grid gap-5 lg:grid-cols-2">
    @include('warden::partials.card-open', ['title' => __('warden::project.schedule.heartbeats_title'), 'action' => null])
        @forelse($heartbeats as $hb)
            <div class="flex items-center gap-2.5 border-t border-ink-700/70 px-4 py-3 text-sm first:border-0">
                <span class="h-2 w-2 rounded-full {{ $hb['healthy'] ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                <span class="truncate text-slate-200">{{ Str::after($hb['key'], 'schedule:') }}</span>
                <span class="ml-auto text-[11px] text-slate-500">{{ __('warden::project.schedule.interval_ago', ['intervals' => $hb['interval'].'s', 'ago' => Format::ago($hb['last_seen'])]) }}</span>
            </div>
        @empty
            <p class="px-4 py-10 text-center text-sm text-slate-600">{{ __('warden::project.schedule.heartbeats_empty') }}</p>
        @endforelse
    @include('warden::partials.card-close')

    @include('warden::partials.card-open', ['title' => __('warden::project.schedule.tasks_title'), 'action' => null])
        @if($tasks->isEmpty())
            <p class="px-4 py-10 text-center text-sm text-slate-600">{{ __('warden::project.schedule.tasks_empty') }}</p>
        @else
            <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="bg-ink-850 text-[11px] uppercase tracking-wider text-slate-500">
                    <th class="px-4 py-3 text-left font-medium">{{ __('warden::project.schedule.col_task') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.schedule.col_runs') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.schedule.col_avg') }}</th>
                </tr></thead>
                <tbody class="divide-y divide-ink-700/70">
                    @foreach($tasks as $t)
                        <tr class="transition hover:bg-ink-850/50">
                            <td class="px-4 py-3 font-mono text-[12px] text-slate-200">{{ $t['key'] }}</td>
                            <td class="px-4 py-3 text-right text-slate-300">{{ Format::num($t['count']) }}</td>
                            <td class="px-4 py-3 text-right text-slate-400">{{ Format::dur($t['avg']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    @include('warden::partials.card-close')
</div>

<div class="mt-6">
    @include('warden::partials.event-list', ['events' => $recent, 'type' => 'schedule', 'title' => __('warden::project.schedule.recent_title')])
</div>
