@php
    use VictorStochero\Warden\Dashboard\Format;
    $levelTone = ['emergency' => 'rose', 'alert' => 'rose', 'critical' => 'rose', 'error' => 'rose', 'warning' => 'amber', 'notice' => 'sky', 'info' => 'sky', 'debug' => 'slate'];
    $active = $activeLevel ?? null;
@endphp
@include('warden::partials.card-open', ['title' => 'Logs by level', 'action' => $active ? ['Clear filter', request()->fullUrlWithQuery(['level' => null])] : null])
    @if($levels->isEmpty())
        <p class="px-4 py-6 text-center text-sm text-slate-600">No logs in range</p>
    @else
        @php $total = max(1, $levels->sum('count')); @endphp
        <div class="divide-y divide-ink-700/70">
            @foreach($levels as $l)
                @php $tone = $levelTone[$l['key']] ?? 'slate'; $isActive = $active === $l['key']; @endphp
                <a href="{{ request()->fullUrlWithQuery(['level' => $l['key']]) }}"
                   class="flex items-center gap-3 px-4 py-3 transition hover:bg-ink-800 {{ $isActive ? 'bg-ink-800' : '' }}">
                    <span class="w-20 text-xs font-medium uppercase tracking-wider text-{{ $tone }}-400">{{ $l['key'] }}</span>
                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-ink-700">
                        <div class="h-full rounded-full bg-{{ $tone }}-500" style="width: {{ round($l['count'] / $total * 100) }}%"></div>
                    </div>
                    <span class="w-16 text-right text-sm {{ $isActive ? 'text-white' : 'text-slate-300' }}">{{ Format::num($l['count']) }}</span>
                </a>
            @endforeach
        </div>
    @endif
@include('warden::partials.card-close')

<div class="mt-5">
    @include('warden::partials.event-list', ['events' => $recent, 'type' => 'log', 'title' => $active ? 'Recent logs · ' . $active : 'Recent logs'])
</div>
