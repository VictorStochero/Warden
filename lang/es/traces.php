<?php

return [
    'page_title' => 'Traces',
    'heading' => ':name · Traces',

    'intro' => 'Cada trace es un <span class="text-slate-300">punto de entrada</span> — una solicitud HTTP, comando de consola, tarea programada o job en cola. Ábrelo para ver su línea de tiempo completa (queries, caché, logs, correo, HTTP saliente…).',

    'list' => [
        'empty' => 'Aún no se han capturado traces',
        'empty_short' => 'No hay traces capturados',
    ],

    'badge' => [
        'error' => 'error',
        'err' => 'err',
        'errored' => 'con error',
    ],

    'detail' => [
        'title' => 'Trace',
        'heading' => 'Línea de tiempo del Trace',
        'back' => '← Traces',
        'summary' => ':count spans · :duration total',
        'view_event' => 'Ver detalle del evento',
        'issue_chip' => 'issue',
        'view_issue_title' => 'Agrupada bajo un issue (:count eventos) — verlo',
        'n_plus_one_label' => 'N+1 ×:count',
        'n_plus_one_title' => 'Repetido :count× en este trace',
    ],
];
