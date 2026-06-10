<div class="space-y-6">
    @include('warden::partials.card-open', ['title' => __('warden::project.queries.slowest_title'), 'action' => null])
        @include('warden::partials.query-table', ['queries' => $slow])
    @include('warden::partials.card-close')

    @include('warden::partials.card-open', ['title' => __('warden::project.queries.expensive_title'), 'action' => null])
        @include('warden::partials.query-table', ['queries' => $frequent])
    @include('warden::partials.card-close')
</div>
