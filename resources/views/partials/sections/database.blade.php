<div class="space-y-8">
    <section class="space-y-6">
        <h2 class="text-[11px] font-semibold uppercase tracking-widest text-slate-500">{{ __('warden::project.database.queries_heading') }}</h2>
        @include('warden::partials.card-open', ['title' => __('warden::project.queries.slowest_title'), 'action' => null])
            @include('warden::partials.query-table', ['queries' => $slow])
        @include('warden::partials.card-close')

        @include('warden::partials.card-open', ['title' => __('warden::project.queries.expensive_title'), 'action' => null])
            @include('warden::partials.query-table', ['queries' => $frequent])
        @include('warden::partials.card-close')
    </section>

    <section class="space-y-6">
        <h2 class="text-[11px] font-semibold uppercase tracking-widest text-slate-500">{{ __('warden::project.database.cache_heading') }}</h2>
        @include('warden::partials.card-open', ['title' => __('warden::project.cache.title'), 'action' => null])
            @include('warden::partials.cache-table', ['stores' => $stores])
        @include('warden::partials.card-close')
    </section>
</div>
