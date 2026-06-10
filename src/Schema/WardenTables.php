<?php

namespace VictorStochero\Warden\Schema;

/**
 * The canonical list of every table Warden owns. It is the single source of
 * truth for destructive maintenance (warden:switch / warden:uninstall): the
 * names are otherwise only implied by the migration filenames. A guard test
 * asserts this list stays in sync with the create_wdn_*_table migrations, so
 * adding a table without listing it here fails CI rather than silently leaking
 * an orphan table past a reinstall.
 */
class WardenTables
{
    /** @return list<string> */
    public static function all(): array
    {
        return [
            'wdn_projects',
            'wdn_events',
            'wdn_aggregates',
            'wdn_issues',
            'wdn_heartbeats',
            'wdn_incidents',
            'wdn_outbox',
            'wdn_cursors',
            'wdn_command_runs',
            'wdn_ingested_batches',
            'wdn_dead_letter',
            'wdn_groups',
            'wdn_tags',
            'wdn_project_tag',
            'wdn_alert_settings',
        ];
    }
}
