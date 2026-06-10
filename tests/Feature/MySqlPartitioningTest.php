<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use VictorStochero\Warden\Schema\SchemaManager;
use VictorStochero\Warden\Tests\TestCase;

class MySqlPartitioningTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('warden.parent.partitioning', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection('testing')->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Partitioning is MySQL-only.');
        }
    }

    public function test_partition_command_partitions_table_and_prune_returns_int(): void
    {
        // Before: portable table, not yet partitioned.
        $manager = SchemaManager::make(DB::connection('testing'), $this->app['config']);
        $this->assertFalse($manager->prunesByPartition());

        // Convert + pre-create partitions.
        $this->artisan('warden:partition')->assertSuccessful();

        // wdn_events is now RANGE-partitioned on occurred_date.
        $create = (array) DB::connection('testing')->selectOne('SHOW CREATE TABLE wdn_events');
        $ddl = strtoupper(implode(' ', array_map('strval', $create)));
        $this->assertStringContainsString('PARTITION', $ddl);

        // A fresh manager now reports partition-based pruning, and prune returns an int.
        $fresh = SchemaManager::make(DB::connection('testing'), $this->app['config']);
        $this->assertTrue($fresh->prunesByPartition());
        $this->assertIsInt($fresh->pruneOlderThan(now()->subDays(30)));
    }
}
