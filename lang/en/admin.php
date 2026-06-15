<?php

return [

    'projects' => [
        'title' => 'Projects',
        'heading' => 'Projects',
        'subheading' => 'Create and manage observed projects',

        'credentials_shown_once' => 'Credentials for :name — shown only once',
        'option_a_title' => 'Option A · install command',
        'option_a_description' => 'Run on the child — writes the config files and the .env keys for you:',
        'option_b_title' => 'Option B · .env keys',
        'option_b_description' => 'Or run <code>warden:install</code> locally to generate the config, then paste these straight into production\'s <code>.env</code>:',

        'add_project' => 'Add project',
        'col_name' => 'Name',
        'col_slug' => 'Slug',
        'col_status' => 'Status',
        'col_last_seen' => 'Last seen',
        'col_settings' => 'Settings',
        'col_actions' => 'Actions',

        'status_active' => 'active',
        'status_inactive' => 'inactive',
        'last_seen_never' => 'never',

        'label_audit' => 'Audit',
        'label_tz' => 'TZ',
        'tz_auto' => 'Auto-detecting…',
        'run_now' => 'Run now',
        'btn_edit' => 'Edit',
        'btn_credentials' => 'Credentials',
        'btn_rotate' => 'Rotate',
        'btn_deactivate' => 'Deactivate',
        'btn_activate' => 'Activate',
        'btn_reset_metrics' => 'Reset metrics',
        'btn_delete' => 'Delete',

        'confirm_rotate' => 'Rotate credentials for :name? The current token and secret stop working immediately — the child must be reconfigured with the new ones.',
        'confirm_deactivate' => 'Deactivate :name? Incoming batches will be rejected while inactive.',
        'confirm_activate' => 'Activate :name?',
        'confirm_reset' => 'Reset ALL saved metrics for :name? Raw events, rollups, issues, incidents, heartbeats and cursors are permanently deleted. Credentials are kept.',
        'confirm_delete' => 'Permanently delete :name and ALL of its data (events, rollups, issues, incidents, heartbeats, cursors)? This cannot be undone.',
        'deleted' => ':name and all its data were deleted.',
        'cannot_delete_self' => "The parent's self-monitoring project can't be deleted.",

        'no_projects' => 'No projects yet.',

        'modal_add_title' => 'Add project',
        'modal_name_label' => 'Name',
        'modal_name_placeholder' => 'My App',
        'modal_slug_label' => 'Slug (optional)',
        'modal_create_btn' => 'Create project',

        'audit_off' => 'Off',
    ],

    'edit' => [
        'title' => 'Edit project',
        'heading' => 'Edit project',

        'section_details' => 'Details',

        'name_label' => 'Name',
        'client_label' => 'Client',
        'client_placeholder' => 'e.g. Acme Inc.',
        'contact_label' => 'Contact',
        'contact_placeholder' => 'name or e-mail',
        'group_label' => 'Group',
        'group_placeholder' => 'type to create or pick',
        'group_help' => 'Projects with the same group are clustered on the overview. Leave empty for none.',
        'tags_label' => 'Tags',
        'tags_placeholder' => 'comma-separated, e.g. prod, billing',
        'tags_existing' => 'Existing:',

        'section_intervals' => 'Intervals',
        'intervals_help' => 'When the security audit runs, and the window for the uptime KPI. Times are in this project\'s timezone.',

        'audit_frequency_label' => 'Audit frequency',
        'audit_day_of_week' => 'Day of week',
        'audit_day_of_month' => 'Day of month',
        'audit_hour_label' => 'Hour',
        'audit_any_hour' => 'Any hour',

        'freq_off' => 'Off',
        'freq_daily' => 'Daily',
        'freq_weekly' => 'Weekly',
        'freq_monthly' => 'Monthly',

        'weekday_0' => 'Sunday',
        'weekday_1' => 'Monday',
        'weekday_2' => 'Tuesday',
        'weekday_3' => 'Wednesday',
        'weekday_4' => 'Thursday',
        'weekday_5' => 'Friday',
        'weekday_6' => 'Saturday',

        'uptime_window_label' => 'Uptime window',
        'uptime_24h' => 'Last 24 hours',
        'uptime_7d' => 'Last 7 days',
        'uptime_30d' => 'Last 30 days',
        'uptime_help' => 'Headline availability KPI on the project\'s Uptime section.',

        'section_alerts' => 'Alerts',
        'alerts_help' => 'Override the global e-mail alert settings for this project. Leave fields blank to inherit the global defaults.',

        'alert_override_label' => 'Override e-mail alerts for this project',
        'alert_enabled_label' => 'Enable e-mail alerts',
        'recipients_label' => 'Recipients',
        'recipients_placeholder' => 'leave blank to use global recipients',
        'recipients_help' => 'Comma, semicolon or newline separated.',
        'min_severity_label' => 'Minimum severity',
        'min_severity_inherit' => 'Inherit global',
    ],

    'audit' => [
        'title' => 'Audit log',
        'heading' => 'Audit log',
        'subheading' => 'Who did what in the dashboard',
        'empty' => 'No actions recorded yet.',
        'col_when' => 'When',
        'col_actor' => 'Actor',
        'col_action' => 'Action',
        'col_target' => 'Target',
        'col_ip' => 'IP',
    ],

    'maintenance' => [
        'title' => 'Maintenance',
        'heading' => 'Maintenance',
        'subheading' => 'Trigger parent maintenance commands on demand',

        'intro' => 'Commands run on the queue. No worker? The scheduler already runs these automatically.',
        'never_run' => 'never run',
        'last_output' => 'Last run output',
        'no_output' => 'Completed with no output.',
        'run_now' => 'Run now',

        'confirm_prune' => 'Run warden:prune? It permanently deletes raw events and aggregates past their retention window.',

        'dead_letter_title' => 'Dropped batches (dead-letter)',
        'dead_letter_empty' => 'None — all batches delivered.',
        'col_batch' => 'Batch',
        'col_reason' => 'Reason',
        'col_attempts' => 'Attempts',
        'col_reported' => 'Reported',
    ],

    'settings' => [
        'title' => 'Alert settings',
        'heading' => 'Alert settings',
        'subheading' => 'Global e-mail alert channel',

        'section_email' => 'E-mail alerts',
        'email_help' => 'Incident transitions are e-mailed through this app\'s configured mailer. Individual projects can override these defaults on their edit page.',
        'email_enabled_label' => 'Enable e-mail alerts',

        'recipients_label' => 'Recipients',
        'recipients_placeholder' => 'ops@example.com, oncall@example.com',
        'recipients_help' => 'Comma, semicolon or newline separated.',

        'min_severity_label' => 'Minimum severity',
        'min_severity_help' => 'Only incidents at or above this severity are e-mailed.',

        'cooldown_label' => 'Cooldown (seconds)',
        'cooldown_help' => 'Minimum gap between repeat alerts for the same incident.',

        'save_btn' => 'Save settings',
        'rules_title' => 'Alert rules',
        'rules_help' => 'Open an incident when a metric crosses a threshold over a window. Evaluated alongside config-defined rules.',
        'rules_name' => 'Rule name',
        'rules_add' => 'Add rule',
        'rules_remove' => 'Remove',
    ],

    'confirm_modal' => [
        'default_message' => 'Are you sure?',
        'cancel' => 'Cancel',
        'confirm' => 'Confirm',
    ],

];
