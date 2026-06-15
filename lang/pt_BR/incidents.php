<?php

return [
    'list' => [
        'title' => 'Incidentes',
        'heading' => 'Incidentes',
        'description' => 'Incidentes são abertos automaticamente a partir de :issues (exceções não tratadas) e :heartbeats (uma tarefa agendada que parou de reportar). Eles se resolvem sozinhos quando a causa subjacente desaparece.',
        'description_issues' => 'issues',
        'description_heartbeats' => 'heartbeats',
        'empty' => 'Nenhum incidente 🎉',
    ],

    'show' => [
        'title' => 'Incidente',
        'heading' => 'Incidente',
        'back' => '← Todos os incidentes',
        'resolve_button' => 'Resolver',
        'resolve_confirm' => 'Marcar este incidente como resolvido? Se a causa subjacente ainda estiver ativa, ele será reaberto na próxima avaliação.',
        'dt_started' => 'Iniciado',
        'dt_resolved' => 'Resolvido',
        'dt_last_alerted' => 'Último alerta',
        'never' => 'nunca',
        'view_related_issue' => 'Ver a issue relacionada →',
        'view_error_trace' => 'Ver o trace do erro →',
        'heartbeat_description' => 'Monitora o heartbeat :name — uma tarefa agendada que parou de reportar no prazo.',
        'view_scheduled_tasks' => 'Ver tarefas agendadas →',
        'details_label' => 'Detalhes',
    ],
];
