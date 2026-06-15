@include('warden::partials.card-open', ['title' => __('warden::project.jobs.title'), 'action' => null])
    @include('warden::partials.queue-table', ['queues' => $queues, 'project' => $project])
@include('warden::partials.card-close')

<div class="mt-6">
    @include('warden::partials.event-list', ['events' => $recent, 'type' => 'job', 'title' => __('warden::project.jobs.recent_title')])
</div>
