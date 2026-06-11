<?php

return [
    'subheading' => 'Fleet health across all observed projects',

    'kpi' => [
        'projects' => 'Projects',
        'throughput' => 'Throughput · 5m',
        'requests' => 'requests',
        'open_issues' => 'Open issues',
        'open_incidents' => 'Open incidents',
    ],

    'projects_heading' => 'Projects',
    'ungrouped' => 'Ungrouped',

    'filter' => [
        'group' => 'Group',
        'tag' => 'Tag',
        'all' => 'All',
    ],

    'empty' => [
        'no_match' => 'No projects match this filter.',
        'none_registered' => 'No projects registered yet.',
        'clear_filters' => 'Clear filters',
        'hint' => 'Create a project, hand its token + secret to a child app, and run :ship.',
    ],
];
