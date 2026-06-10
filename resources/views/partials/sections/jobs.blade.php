@include('warden::partials.card-open', ['title' => 'Jobs & queues', 'action' => null])
    @include('warden::partials.queue-table', ['queues' => $queues])
@include('warden::partials.card-close')

<div class="mt-5">
    @include('warden::partials.event-list', ['events' => $recent, 'type' => 'job', 'title' => 'Recent jobs'])
</div>
