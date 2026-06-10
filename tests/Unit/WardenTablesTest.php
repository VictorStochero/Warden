<?php

namespace VictorStochero\Warden\Tests\Unit;

use VictorStochero\Warden\Schema\WardenTables;
use VictorStochero\Warden\Tests\TestCase;

class WardenTablesTest extends TestCase
{
    /**
     * Guard: the canonical list must match exactly the tables created by the
     * create_wdn_*_table migrations. Add a table without listing it here and
     * this fails — keeping warden:switch / warden:uninstall from leaking an
     * orphan table past a rebuild.
     */
    public function test_all_matches_the_create_migrations(): void
    {
        $dir = __DIR__.'/../../database/migrations';

        $fromMigrations = [];
        foreach (glob($dir.'/*_create_wdn_*_table.php') ?: [] as $file) {
            if (preg_match('/create_(wdn_[a-z_]+?)_table\.php$/', basename($file), $m) === 1) {
                $fromMigrations[] = $m[1];
            }
        }

        $listed = WardenTables::all();

        sort($fromMigrations);
        sort($listed);

        $this->assertSame($fromMigrations, $listed);
    }
}
