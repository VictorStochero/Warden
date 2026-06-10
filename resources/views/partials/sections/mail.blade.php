@php use VictorStochero\Warden\Dashboard\Format; @endphp
<div class="grid gap-5 lg:grid-cols-2">
    @include('warden::partials.card-open', ['title' => 'Mailers', 'action' => null])
        @if($mailers->isEmpty())
            <p class="px-4 py-6 text-center text-sm text-slate-600">No mail sent in range</p>
        @else
            @foreach($mailers as $m)
                <div class="flex items-center justify-between border-t border-ink-700 px-4 py-3 text-sm first:border-0">
                    <span class="font-mono text-[12px] text-slate-200">{{ $m['key'] }}</span>
                    <span class="text-slate-300">{{ Format::num($m['count']) }} sent · {{ Format::dur($m['avg']) }} avg</span>
                </div>
            @endforeach
        @endif
    @include('warden::partials.card-close')

    @include('warden::partials.card-open', ['title' => 'Notifications', 'action' => null])
        @if($notifications->isEmpty())
            <p class="px-4 py-6 text-center text-sm text-slate-600">No notifications in range</p>
        @else
            @foreach($notifications as $n)
                <div class="flex items-center justify-between border-t border-ink-700 px-4 py-3 text-sm first:border-0">
                    <span class="font-mono text-[12px] text-slate-200">{{ $n['key'] }}</span>
                    <span class="text-slate-300">{{ Format::num($n['count']) }}</span>
                </div>
            @endforeach
        @endif
    @include('warden::partials.card-close')
</div>

<div class="mt-5 grid gap-5 lg:grid-cols-2">
    @include('warden::partials.event-list', ['events' => $recent_mail, 'type' => 'mail', 'title' => 'Recent mail'])
    @include('warden::partials.event-list', ['events' => $recent_notifications, 'type' => 'notification', 'title' => 'Recent notifications'])
</div>
