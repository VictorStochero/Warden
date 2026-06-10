<div class="space-y-5">
    @include('warden::partials.card-open', ['title' => 'Slowest queries (by average)', 'action' => null])
        @include('warden::partials.query-table', ['queries' => $slow])
    @include('warden::partials.card-close')

    @include('warden::partials.card-open', ['title' => 'Most expensive queries (cumulative)', 'action' => null])
        @include('warden::partials.query-table', ['queries' => $frequent])
    @include('warden::partials.card-close')
</div>
