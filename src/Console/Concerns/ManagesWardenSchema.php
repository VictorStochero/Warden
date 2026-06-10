<?php

namespace VictorStochero\Warden\Console\Concerns;

use Illuminate\Console\Command;
use VictorStochero\Warden\Schema\WardenTables;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Schema;
use VictorStochero\Warden\Warden;

/**
 * The destructive half of warden:switch and warden:uninstall. Dropping the
 * tables is not enough on its own: the migrator records each migration in the
 * framework's `migrations` table, so a later `migrate` would consider them
 * already run and never recreate the schema. Forgetting those rows is what lets
 * the schema be rebuilt from zero. All of it runs on the configured warden
 * connection and inside withoutRecording() so a self-monitoring parent never
 * observes its own teardown (§18.3).
 *
 * @phpstan-require-extends Command
 */
trait ManagesWardenSchema
{
    /**
     * Tables from WardenTables::all() that currently exist, for the confirmation prompt.
     *
     * @return list<string>
     */
    protected function existingWardenTables(): array
    {
        $schema = Schema::connection();

        return array_values(array_filter(
            WardenTables::all(),
            fn (string $table): bool => $schema->hasTable($table)
        ));
    }

    /** Drop every wdn_ table and forget its migration rows so the schema can be rebuilt. */
    protected function dropWardenSchema(): void
    {
        $this->laravel->make(Warden::class)->withoutRecording(function (): void {
            $schema = Schema::connection();

            foreach (WardenTables::all() as $table) {
                $schema->dropIfExists($table);
            }

            $this->forgetWardenMigrations();
        });
    }

    /** Delete Warden's rows from the migrations table (every filename contains "wdn"). */
    protected function forgetWardenMigrations(): void
    {
        $table = $this->migrationsTable();
        $db = Schema::db();

        if (! $db->getSchemaBuilder()->hasTable($table)) {
            return;
        }

        $db->table($table)->where('migration', 'like', '%wdn%')->delete();
    }

    /** The framework migrations table, honouring a custom name (string or ['table' => ...]). */
    private function migrationsTable(): string
    {
        $config = config('database.migrations');

        if (is_array($config)) {
            return Cast::str($config['table'] ?? null, 'migrations');
        }

        return Cast::str($config, 'migrations');
    }
}
