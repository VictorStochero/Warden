<?php

namespace VictorStochero\Warden\Schema;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;

/**
 * The "cost of only-the-RDBMS": partitioning and pruning differ per driver, so
 * they live behind this abstraction (§18.5).
 *
 *  - MySQL: RANGE partitioning on occurred_date; prune = DROP PARTITION.
 *  - Postgres / SQLite: single table; prune = DELETE by occurred_at.
 *
 * The base class provides the portable DELETE path; MySqlSchemaManager overrides
 * with native partitioning on wdn_events.
 */
class SchemaManager
{
    public function __construct(
        protected Connection $db,
        protected Repository $config,
    ) {}

    public static function make(Connection $db, Repository $config): self
    {
        return match ($db->getDriverName()) {
            'mysql', 'mariadb' => new MySqlSchemaManager($db, $config),
            default => new self($db, $config),
        };
    }

    /** Whether pruning drops whole partitions (cheap) vs DELETEs rows. */
    public function prunesByPartition(): bool
    {
        return false;
    }

    /** Convert wdn_events to a partitioned table if the driver supports it. */
    public function ensurePartitioned(): void
    {
        // No-op on drivers without (retrofittable) partitioning.
    }

    /** Pre-create partitions covering the next N days. */
    public function createPartitionsAhead(int $days): void
    {
        // No-op unless overridden.
    }

    /**
     * Remove raw events older than the cutoff. Default portable path: DELETE in
     * bounded chunks so a large prune never locks the table for long.
     */
    public function pruneOlderThan(Carbon $cutoff): int
    {
        $deleted = 0;

        do {
            $count = $this->db->table('wdn_events')
                ->where('occurred_at', '<', $cutoff)
                ->limit(5000)
                ->delete();

            $deleted += $count;
        } while ($count > 0);

        return $deleted;
    }

    protected function partitioningEnabled(): bool
    {
        return (bool) $this->config->get('warden.parent.partitioning', true);
    }
}
