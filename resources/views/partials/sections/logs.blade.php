@php
    use VictorStochero\Warden\Dashboard\Format;
    $levelTone = ['emergency' => 'rose', 'alert' => 'rose', 'critical' => 'rose', 'error' => 'rose', 'warning' => 'amber', 'notice' => 'sky', 'info' => 'sky', 'debug' => 'slate'];
    $active = $activeLevel ?? null;
@endphp
@include('warden::partials.card-open', ['title' => __('warden::project.logs.title'), 'action' => $active ? [__('warden::project.logs.clear_filter'), request()->fullUrlWithQuery(['level' => null])] : null])
    @if($levels->isEmpty())
        <p class="px-4 py-8 text-center text-sm text-slate-600">{{ __('warden::project.logs.empty') }}</p>
    @else
        @php $total = max(1, $levels->sum('count')); @endphp
        <div class="divide-y divide-ink-700/70">
            @foreach($levels as $l)
                @php $tone = $levelTone[$l['key']] ?? 'slate'; $isActive = $active === $l['key']; @endphp
                <a href="{{ request()->fullUrlWithQuery(['level' => $l['key']]) }}"
                   class="flex items-center gap-3 px-4 py-3 transition hover:bg-ink-850/50 {{ $isActive ? 'bg-ink-850' : '' }}">
                    <span class="w-20 text-xs font-medium uppercase tracking-wider text-{{ $tone }}-400">{{ $l['key'] }}</span>
                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-ink-850">
                        <div class="h-full rounded-full bg-{{ $tone }}-500" style="width: {{ round($l['count'] / $total * 100) }}%"></div>
                    </div>
                    <span class="w-16 text-right text-sm {{ $isActive ? 'text-white' : 'text-slate-300' }}">{{ Format::num($l['count']) }}</span>
                </a>
            @endforeach
        </div>
    @endif
@include('warden::partials.card-close')

<form method="GET" class="mt-6 flex items-center gap-2">
    @if($active)<input type="hidden" name="level" value="{{ $active }}">@endif
    <input type="search" name="q" value="{{ $activeSearch ?? '' }}" placeholder="{{ __('warden::project.logs.search_placeholder') }}"
           class="w-full max-w-sm rounded-lg border border-ink-700 bg-ink-850 px-3 py-1.5 text-sm text-slate-200 placeholder-slate-600">
    <button type="submit" class="rounded-lg border border-ink-700 px-3 py-1.5 text-xs font-medium text-slate-300 transition hover:border-slate-500 hover:text-white">{{ __('warden::project.logs.search') }}</button>
    @if($activeSearch ?? null)
        <a href="{{ request()->fullUrlWithQuery(['q' => null]) }}" class="text-xs text-slate-500 hover:text-slate-300">{{ __('warden::project.logs.clear_filter') }}</a>
    @endif
</form>

<div class="mt-4">
    @include('warden::partials.event-list', ['events' => $recent, 'type' => 'log', 'title' => $active ? __('warden::project.logs.recent_filtered_title', ['level' => $active]) : __('warden::project.logs.recent_title')])
</div>
