<?php

return [
    'list' => [
        'title' => 'Incidentes',
        'heading' => 'Incidentes',
        'description' => 'Los incidentes se abren automáticamente desde :issues (excepciones no controladas) y :heartbeats (una tarea programada que dejó de reportar). Se resuelven solos cuando la causa subyacente desaparece.',
        'description_issues' => 'issues',
        'description_heartbeats' => 'heartbeats',
        'empty' => 'No hay incidentes 🎉',
    ],

    'show' => [
        'title' => 'Incidente',
        'heading' => 'Incidente',
        'back' => '← Todos los incidentes',
        'resolve_button' => 'Resolver',
        'resolve_confirm' => '¿Marcar este incidente como resuelto? Si la causa subyacente sigue activa, se reabrirá en la próxima evaluación.',
        'dt_started' => 'Iniciado',
        'dt_resolved' => 'Resuelto',
        'dt_last_alerted' => 'Última alerta',
        'never' => 'nunca',
        'view_related_issue' => 'Ver la issue relacionada →',
        'view_error_trace' => 'Ver el trace del error →',
        'heartbeat_description' => 'Monitorea el heartbeat :name — una tarea programada que dejó de reportar a tiempo.',
        'view_scheduled_tasks' => 'Ver tareas programadas →',
        'details_label' => 'Detalles',
    ],
];
