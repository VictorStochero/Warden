<?php

return [
    'list' => [
        'title' => 'Issues',
        'heading' => 'Issues',
        'description' => 'Issues are :unhandled grouped by fingerprint. An empty list means none were reported in this window — that\'s healthy, not a misconfiguration.',
        'description_unhandled' => 'unhandled exceptions',
        'empty' => 'No :status issues',
        'tab_open' => 'Open',
        'tab_resolved' => 'Resolved',
        'tab_ignored' => 'Ignored',
        'col_issue' => 'Issue',
        'col_events' => 'Events',
        'col_users' => 'Users',
        'col_last_seen' => 'Last seen',
    ],

    'show' => [
        'back' => '← All issues',
        'stat_events' => 'Events',
        'stat_users_affected' => 'Users affected',
        'stat_first_seen' => 'First seen',
        'stat_last_seen' => 'Last seen',
        'view_last_trace' => 'View last trace',
        'stack_trace' => 'Stack trace',
    ],

    'comments' => [
        'heading' => 'Comments',
        'empty' => 'No comments yet.',
        'placeholder' => 'Add a note…',
        'submit' => 'Comment',
    ],

    'actions' => [
        'resolve' => 'Resolve',
        'ignore' => 'Ignore',
        'reopen' => 'Reopen',
        'snooze' => 'Snooze',
        'assign' => 'Assign',
        'assignee_placeholder' => 'Assignee…',
        'snoozed_note' => 'Muted from alerting while snoozed.',
        'status_resolved' => 'Issue resolved.',
        'status_ignored' => 'Issue ignored.',
        'status_reopened' => 'Issue reopened.',
        'status_assigned' => 'Assignee updated.',
        'status_snoozed' => 'Issue snoozed.',
        'status_commented' => 'Comment added.',
    ],
];
