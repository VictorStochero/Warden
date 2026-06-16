<?php

return [
    'list' => [
        'title' => 'Issues',
        'heading' => 'Issues',
        'description' => 'Las Issues son :unhandled agrupadas por fingerprint. Una lista vacía significa que no se reportó ninguna en esta ventana — eso es saludable, no una configuración incorrecta.',
        'description_unhandled' => 'excepciones no controladas',
        'empty' => 'No hay issues con estado :status',
        'tab_open' => 'Abiertas',
        'tab_resolved' => 'Resueltas',
        'tab_ignored' => 'Ignoradas',
        'col_issue' => 'Issue',
        'col_events' => 'Eventos',
        'col_users' => 'Usuarios',
        'col_last_seen' => 'Última vez visto',
    ],

    'show' => [
        'back' => '← Todas las issues',
        'stat_events' => 'Eventos',
        'stat_users_affected' => 'Usuarios afectados',
        'stat_first_seen' => 'Primera vez visto',
        'stat_last_seen' => 'Última vez visto',
        'view_last_trace' => 'Ver último trace',
        'stack_trace' => 'Stack trace',
    ],

    'comments' => [
        'heading' => 'Comentarios',
        'empty' => 'Aún no hay comentarios.',
        'placeholder' => 'Agregar una nota…',
        'submit' => 'Comentar',
    ],

    'actions' => [
        'resolve' => 'Resolver',
        'ignore' => 'Ignorar',
        'reopen' => 'Reabrir',
        'snooze' => 'Silenciar',
        'assign' => 'Asignar',
        'assignee_placeholder' => 'Responsable…',
        'snoozed_note' => 'Silenciada para alertas mientras esté en pausa.',
        'status_resolved' => 'Issue resuelta.',
        'status_ignored' => 'Issue ignorada.',
        'status_reopened' => 'Issue reabierta.',
        'status_assigned' => 'Responsable actualizado.',
        'status_snoozed' => 'Issue silenciada.',
        'status_commented' => 'Comentario agregado.',
    ],
];
