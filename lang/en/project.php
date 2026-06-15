<?php

return [

    // ── project.blade.php ────────────────────────────────────────────────
    'subheading' => ':section · last seen :ago',

    'kpi' => [
        'throughput' => 'Throughput',
        'requests' => 'requests',
        'error_rate' => 'Error rate',
        'errors' => ':count errors',
        'p95_latency' => 'p95 latency',
        'slow_reqs' => 'Slow reqs',
        'failed_jobs' => 'Failed jobs',
        'cache_hit' => 'Cache hit',
        'open_issues' => 'Open issues',
        'uptime_30d' => 'Uptime · 30d',
    ],

    // ── sections/overview.blade.php ──────────────────────────────────────
    'overview' => [
        'throughput' => 'Throughput',
        'p95_latency' => 'p95 latency',
        'top_routes' => 'Top routes',
        'requests_action' => 'Requests',
        'slowest_queries' => 'Slowest queries',
        'queries_action' => 'Queries',
        'queues' => 'Queues',
        'active_incidents' => 'Active incidents',
        'recent_issues' => 'Recent issues',
        'all_action' => 'All',
        'no_open_issues' => 'No open issues 🎉',
        'heartbeats' => 'Heartbeats',
        'no_heartbeats' => 'No heartbeats tracked',
        'recent_traces' => 'Recent traces',
        'all_traces_action' => 'All',
    ],

    // ── sections/cache.blade.php ─────────────────────────────────────────
    'cache' => [
        'title' => 'Cache stores',
        'empty' => 'No cache activity in range',
        'col_store' => 'Store',
        'col_hits' => 'Hits',
        'col_misses' => 'Misses',
        'col_writes' => 'Writes',
        'col_hit_rate' => 'Hit rate',
    ],

    // ── sections/delivery.blade.php ──────────────────────────────────────
    'delivery' => [
        'last_received' => 'Last received',
        'never' => 'never',
        'mode_label' => 'Delivery mode',
        'mode_sub' => 'inferred from arrival gaps',
        'batches_label' => 'Batches',
        'events_label' => 'Events',
        'last_window' => 'last :window m',
        'arrivals_chart_label' => 'Arrivals per minute · last :window m',
        'status_receiving' => 'receiving',
        'status_idle' => 'idle',
        'recent_arrivals' => 'Recent arrivals',
        'arrivals_empty' => 'Nothing received in the last :window minutes. If the child is configured, check its <span class="font-mono">warden:ship</span> daemon or scheduler.',
        'col_received' => 'Received',
        'col_when' => 'When',
        'col_batches' => 'Batches',
        'col_events' => 'Events',
        'mode_no_data' => 'No data',
        'mode_continuous' => 'Continuous · daemon',
        'mode_every_minute' => 'Every minute · cron',
        'mode_approx' => '~every :cads',
    ],

    // ── sections/errors.blade.php ────────────────────────────────────────
    'errors' => [
        'definition_html' => '<span class="font-medium text-slate-200">Errors</span> are failed HTTP responses (status 5xx). They are distinct from <a href=":issues_url" class="text-brand-400 hover:text-brand-300">Issues</a> (unhandled exceptions grouped by fingerprint) and <a href=":incidents_url" class="text-brand-400 hover:text-brand-300">Incidents</a> (alerts opened from a down heartbeat or an open issue). A 5xx usually <em>has</em> a matching issue; a 4xx does not.',
        'chart_label' => 'Errors over time · 5xx',
        'routes_title' => 'Routes with errors',
        'routes_empty' => 'No 5xx errors in range 🎉',
        'recent_title' => 'Recent 5xx requests',
        'exceptions_title' => 'Recent exceptions',
        'release_filter' => 'Since release',
        'release_all' => 'All',
    ],

    // ── sections/host.blade.php ──────────────────────────────────────────
    'host' => [
        'empty' => 'No host metrics in range. The host recorder samples <code class="text-brand-400">/proc</code> on Linux.',
        'cpu' => 'CPU',
        'memory' => 'Memory',
        'load_1m' => 'Load (1m)',
        'disk' => 'Disk',
        'cpu_chart' => 'CPU %',
        'memory_chart' => 'Memory %',
    ],

    // ── sections/http.blade.php ──────────────────────────────────────────
    'http' => [
        'title' => 'Outgoing HTTP',
        'empty' => 'No outgoing requests in range',
        'col_host' => 'Host',
        'col_calls' => 'Calls',
        'col_errors' => 'Errors',
        'col_avg' => 'Avg',
        'col_max' => 'Max',
        'recent_title' => 'Recent outgoing calls',
    ],

    // ── sections/jobs.blade.php ──────────────────────────────────────────
    'jobs' => [
        'title' => 'Jobs & queues',
        'recent_title' => 'Recent jobs',
    ],

    // ── sections/logs.blade.php ──────────────────────────────────────────
    'logs' => [
        'title' => 'Logs by level',
        'clear_filter' => 'Clear filter',
        'empty' => 'No logs in range',
        'recent_title' => 'Recent logs',
        'recent_filtered_title' => 'Recent logs · :level',
        'search' => 'Search',
        'search_placeholder' => 'Search log messages…',
    ],

    // ── sections/mail.blade.php ──────────────────────────────────────────
    'mail' => [
        'mailers_title' => 'Mailers',
        'mailers_empty' => 'No mail sent in range',
        'notifications_title' => 'Notifications',
        'notifications_empty' => 'No notifications in range',
        'recent_mail_title' => 'Recent mail',
        'recent_notif_title' => 'Recent notifications',
        'sent_avg' => ':count sent · :avg avg',
    ],

    // ── sections/queries.blade.php ───────────────────────────────────────
    'queries' => [
        'slowest_title' => 'Slowest queries (by average)',
        'expensive_title' => 'Most expensive queries (cumulative)',
    ],

    // ── sections/requests.blade.php ──────────────────────────────────────
    'requests' => [
        'throughput' => 'Throughput',
        'errors' => 'Errors',
        'p95_latency' => 'p95 latency',
        'deploys' => 'Deploys',
        'routes_title' => 'Routes',
        'recent_title' => 'Recent requests',
    ],

    // ── sections/schedule.blade.php ──────────────────────────────────────
    'schedule' => [
        'heartbeats_title' => 'Heartbeats',
        'heartbeats_empty' => 'No heartbeats tracked yet',
        'tasks_title' => 'Scheduled tasks',
        'tasks_empty' => 'No task runs in range',
        'col_task' => 'Task',
        'col_runs' => 'Runs',
        'col_avg' => 'Avg',
        'recent_title' => 'Recent task runs',
        'interval_ago' => 'every :intervals · :ago',
    ],

    // ── sections/security.blade.php ──────────────────────────────────────
    'security' => [
        'run_audit_btn' => 'Run audit now',
        'empty_title' => 'No security audit shipped yet.',
        'empty_hint_html' => 'Run <code class="text-brand-400">php artisan warden:audit</code> on the child, or set an audit frequency under <span class="text-slate-400">Manage projects → Audit</span> to have the child run it automatically.',
        'last_audit' => 'Last audit :ago',
        'tool_ran' => 'ran',
        'tool_ran_packagist' => 'audited (Packagist API)',
        'tool_skipped' => 'skipped',
        'reason_no_package_json' => 'no package.json',
        'reason_npm_not_found' => 'npm not found',
        'reason_composer_not_found' => 'composer not found',
        'reason_network_error' => 'network error',
        'reason_lock_missing' => 'no composer.lock',
        'vulnerabilities_title' => 'Vulnerabilities (:total)',
        'no_vulnerabilities' => 'No known vulnerabilities 🎉',
        'col_severity' => 'Severity',
        'col_package' => 'Package',
        'col_advisory' => 'Advisory',
        'col_affected' => 'Affected',
        'fix_upgrade' => 'Update to :version or later',
        'fix_upgrade_above' => 'Update to a version above :version',
        'fix_available' => 'Fix available — run the package update',
        'fix_none' => 'No known fix yet',
    ],

    // ── sections/uptime.blade.php ────────────────────────────────────────
    'uptime' => [
        'kpi_suffix' => '· KPI',
        'last_window' => 'last :label',
        'definition_html' => 'Availability = share of time with no open <span class="text-slate-300">critical</span> incident (a down heartbeat or a high-severity issue). HTTP errors alone don\'t reduce uptime — see <a href=":errors_url" class="text-brand-400 hover:text-brand-300">Errors</a>.',
        'downtime_title' => 'Downtime episodes · 30d',
        'incidents_action' => 'Incidents',
        'no_incidents' => 'No critical incidents in the last 30 days 🎉',
        'col_incident' => 'Incident',
        'col_started' => 'Started',
        'col_duration' => 'Duration',
        'col_status' => 'Status',
        'status_ongoing' => 'ongoing',
        'status_resolved' => 'resolved',
    ],

    // ── partials/event-list.blade.php ────────────────────────────────────
    'event_list' => [
        'default_title' => 'Recent events',
        'empty' => 'Nothing captured yet — run some traffic (or <span class="font-mono text-slate-500">warden:demo</span> on the child).',
        'no_subject' => '(no subject)',
        'to' => 'to',
        'via' => 'via',
    ],

    // ── partials/route-table.blade.php ───────────────────────────────────
    'route_table' => [
        'empty' => 'No requests in range',
        'col_route' => 'Route',
        'col_count' => 'Count',
        'col_errors' => 'Errors',
        'col_avg' => 'Avg',
        'col_p95' => 'p95',
    ],

    // ── partials/query-table.blade.php ───────────────────────────────────
    'query_table' => [
        'empty' => 'No queries in range',
        'col_query' => 'Query',
        'col_calls' => 'Calls',
        'col_avg' => 'Avg',
        'col_total' => 'Total',
        'slow_badge' => ':count slow',
    ],

    // ── partials/queue-table.blade.php ───────────────────────────────────
    'queue_table' => [
        'empty' => 'No jobs in range',
        'col_job' => 'Job',
        'col_processed' => 'Processed',
        'col_failed' => 'Failed',
        'col_avg' => 'Avg',
    ],

    // ── partials/bars.blade.php & chart.blade.php ────────────────────────
    'chart' => [
        'no_data' => 'no data in range',
    ],

    // ── partials/overview-cards.blade.php ────────────────────────────────
    'overview_cards' => [
        'req_5m' => 'req · 5m',
        'errors' => 'errors',
        'uptime_30d' => '% uptime · 30d',
    ],

    // ── admin/project-edit.blade.php — Behaviour section ─────────────────
    'behaviour' => [
        'title' => 'Behaviour (advanced)',
        'intro' => "Override the child's capture knobs for this project. The parent pushes these to the child on its next delivery. Leave a field blank to inherit the child's own .env / default.",
        'host_interval' => 'Host metric interval (s)',
        'host_interval_hint' => 'How often /proc is sampled.',
        'sample_request' => 'Sample rate — requests',
        'sample_request_hint' => '0..1 fraction of requests traced.',
        'sample_job' => 'Sample rate — jobs',
        'sample_job_hint' => '0..1 fraction of jobs traced.',
        'slower_ms' => 'Always keep slower than (ms)',
        'slower_ms_hint' => 'Force-keep traces above this latency, overriding sampling.',
        'recorders' => 'Recorders',
        'recorders_hint' => "Check the recorders to enable for this project. Leave all unchecked to inherit the child's own list.",
    ],

];
