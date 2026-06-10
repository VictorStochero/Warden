@php use VictorStochero\Warden\Dashboard\Format; @endphp
<div class="grid gap-5 lg:grid-cols-2">
    @include('warden::partials.card-open', ['title' => __('warden::project.mail.mailers_title'), 'action' => null])
        @if($mailers->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-slate-600">{{ __('warden::project.mail.mailers_empty') }}</p>
        @else
            @foreach($mailers as $m)
                <div class="flex items-center justify-between border-t border-ink-700/70 px-4 py-3 text-sm first:border-0 transition hover:bg-ink-850/50">
                    <span class="font-mono text-[12px] text-slate-200">{{ $m['key'] }}</span>
                    <span class="text-slate-300">{{ __('warden::project.mail.sent_avg', ['count' => Format::num($m['count']), 'avg' => Format::dur($m['avg'])]) }}</span>
                </div>
            @endforeach
        @endif
    @include('warden::partials.card-close')

    @include('warden::partials.card-open', ['title' => __('warden::project.mail.notifications_title'), 'action' => null])
        @if($notifications->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-slate-600">{{ __('warden::project.mail.notifications_empty') }}</p>
        @else
            @foreach($notifications as $n)
                <div class="flex items-center justify-between border-t border-ink-700/70 px-4 py-3 text-sm first:border-0 transition hover:bg-ink-850/50">
                    <span class="font-mono text-[12px] text-slate-200">{{ $n['key'] }}</span>
                    <span class="text-slate-300">{{ Format::num($n['count']) }}</span>
                </div>
            @endforeach
        @endif
    @include('warden::partials.card-close')
</div>

<div class="mt-6 grid gap-5 lg:grid-cols-2">
    @include('warden::partials.event-list', ['events' => $recent_mail, 'type' => 'mail', 'title' => __('warden::project.mail.recent_mail_title')])
    @include('warden::partials.event-list', ['events' => $recent_notifications, 'type' => 'notification', 'title' => __('warden::project.mail.recent_notif_title')])
</div>
