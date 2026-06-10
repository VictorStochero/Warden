<?php

return [
    'list' => [
        'title' => 'Incidents',
        'heading' => 'Incidents',
        'description' => 'Incidents open automatically from :issues (unhandled exceptions) and :heartbeats (a scheduled task that stopped reporting). They resolve on their own when the underlying cause clears.',
        'description_issues' => 'issues',
        'description_heartbeats' => 'heartbeats',
        'empty' => 'No incidents 🎉',
    ],

    'show' => [
        'title' => 'Incident',
        'heading' => 'Incident',
        'back' => '← All incidents',
        'resolve_button' => 'Resolve',
        'resolve_confirm' => 'Mark this incident as resolved? If the underlying cause is still active it will reopen on the next evaluation.',
        'dt_started' => 'Started',
        'dt_resolved' => 'Resolved',
        'dt_last_alerted' => 'Last alerted',
        'never' => 'never',
        'view_related_issue' => 'View the related issue →',
        'heartbeat_description' => 'Tracks the heartbeat :name — a scheduled task that stopped reporting on time.',
        'view_scheduled_tasks' => 'View scheduled tasks →',
        'details_label' => 'Details',
    ],
];
