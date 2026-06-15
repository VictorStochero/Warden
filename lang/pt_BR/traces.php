<?php

return [
    'page_title' => 'Traces',
    'heading' => ':name · Traces',

    'intro' => 'Cada trace é um <span class="text-slate-300">ponto de entrada</span> — uma requisição HTTP, comando de console, tarefa agendada ou job na fila. Abra um para ver sua linha do tempo completa (queries, cache, logs, e-mail, HTTP externo…).',

    'list' => [
        'empty' => 'Nenhum trace capturado ainda',
        'empty_short' => 'Nenhum trace capturado',
    ],

    'badge' => [
        'error' => 'erro',
        'err' => 'err',
        'errored' => 'com erro',
    ],

    'filter' => [
        'active' => 'Filtrando por :dim: :value',
        'clear' => 'limpar',
        'dim' => [
            'route' => 'rota',
            'query' => 'query',
            'http' => 'host HTTP',
            'job' => 'job',
            'cache' => 'store de cache',
        ],
    ],

    'detail' => [
        'title' => 'Trace',
        'heading' => 'Linha do tempo do Trace',
        'back' => '← Traces',
        'summary' => ':count spans · :duration total',
        'cross_app' => 'Entre apps:',
        'n_plus_one_label' => 'N+1 ×:count',
        'n_plus_one_title' => 'Repetido :count× neste trace',
        'view_issue' => 'Ver issue agrupada',
    ],
];
