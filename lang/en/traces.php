<?php

return [
    'page_title' => 'Traces',
    'heading' => ':name · Traces',

    'intro' => 'Each trace is an <span class="text-slate-300">entry point</span> — an HTTP request, console command, scheduled task or queued job. Open one to see its full timeline (queries, cache, logs, mail, outgoing HTTP…).',

    'list' => [
        'empty' => 'No traces captured yet',
        'empty_short' => 'No traces captured',
    ],

    'badge' => [
        'error' => 'error',
        'err' => 'err',
        'errored' => 'errored',
    ],

    'filter' => [
        'active' => 'Filtering by :dim: :value',
        'clear' => 'clear',
        'dim' => [
            'route' => 'route',
            'query' => 'query',
            'http' => 'HTTP host',
            'job' => 'job',
            'cache' => 'cache store',
        ],
    ],

    'detail' => [
        'title' => 'Trace',
        'heading' => 'Trace timeline',
        'back' => '← Traces',
        'summary' => ':count spans · :duration total',
        'cross_app' => 'Across apps:',
        'n_plus_one_label' => 'N+1 ×:count',
        'n_plus_one_title' => 'Repeated :count× in this trace',
        'view_issue' => 'View grouped issue',
    ],
];
