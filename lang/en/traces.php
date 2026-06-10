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

    'detail' => [
        'title' => 'Trace',
        'heading' => 'Trace timeline',
        'back' => '← Traces',
        'summary' => ':count spans · :duration total',
        'n_plus_one_label' => 'N+1 ×:count',
        'n_plus_one_title' => 'Repeated :count× in this trace',
    ],
];
