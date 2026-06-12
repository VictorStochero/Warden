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

    'detail' => [
        'title' => 'Trace',
        'heading' => 'Linha do tempo do Trace',
        'back' => '← Traces',
        'summary' => ':count spans · :duration total',
        'view_event' => 'Ver detalhe do evento',
        'issue_chip' => 'issue',
        'view_issue_title' => 'Agrupada numa issue (:count eventos) — ver',
        'n_plus_one_label' => 'N+1 ×:count',
        'n_plus_one_title' => 'Repetido :count× neste trace',
    ],
];
