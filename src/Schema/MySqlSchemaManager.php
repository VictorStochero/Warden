<?php

namespace VictorStochero\Warden\Schema;

use Illuminate\Support\Carbon;
use VictorStochero\Warden\Support\Cast;

/**
 * MySQL/MariaDB RANGE partitioning of wdn_events on occurred_date.
 *
 * Native partitioning requires every unique key to contain the partition
 * column, so ensurePartitioned() rebuilds the primary key as (id, occurred_date)
 * before applying the partition scheme. Pruning then drops whole partitions —
 * effectively free compared to a DELETE (§18.5).
 */
class MySqlSchemaManager extends SchemaManager
{
    public function prunesByPartition(): bool
    {
        return $this->partitioningEnabled() && $this->isPartitioned();
    }

    public function ensurePartitioned(): void
    {
        if (! $this->partitioningEnabled() || $this->isPartitioned()) {
            return;
        }

        // PK must include the partition column.
        $this->db->statement('ALTER TABLE wdn_events DROP PRIMARY KEY, ADD PRIMARY KEY (id, occurred_date)');

        // Seed with today's partition plus a catch-all MAXVALUE partition.
        $today = Carbon::today();
        $name = $this->partitionName($today);
        $boundary = $today->copy()->addDay()->toDateString();

        $this->db->statement(
            'ALTER TABLE wdn_events PARTITION BY RANGE (TO_DAYS(occurred_date)) ('.
            "PARTITION {$name} VALUES LESS THAN (TO_DAYS('{$boundary}')), ".
            'PARTITION pmax VALUES LESS THAN MAXVALUE)'
        );
    }

    public function createPartitionsAhead(int $days): void
    {
        if (! $this->prunesByPartition()) {
            return;
        }

        $existing = $this->existingPartitions();

        for ($i = 0; $i <= $days; $i++) {
            $day = Carbon::today()->addDays($i);
            $name = $this->partitionName($day);

            if (in_array($name, $existing, true)) {
                continue;
            }

            // Split the MAXVALUE partition to insert the new day's range.
            $boundary = $day->copy()->addDay()->toDateString();

            $this->db->statement(
                'ALTER TABLE wdn_events REORGANIZE PARTITION pmax INTO ('.
                "PARTITION {$name} VALUES LESS THAN (TO_DAYS('{$boundary}')), ".
                'PARTITION pmax VALUES LESS THAN MAXVALUE)'
            );

            $existing[] = $name;
        }
    }

    public function pruneOlderThan(Carbon $cutoff): int
    {
        if (! $this->prunesByPartition()) {
            return parent::pruneOlderThan($cutoff);
        }

        $dropped = 0;

        foreach ($this->existingPartitions() as $name) {
            $date = $this->partitionDate($name);

            if ($date !== null && $date->lt($cutoff->copy()->startOfDay())) {
                $this->db->statement("ALTER TABLE wdn_events DROP PARTITION {$name}");
                $dropped++;
            }
        }

        return $dropped;
    }

    protected function isPartitioned(): bool
    {
        $row = (array) $this->db->selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.partitions '.
            'WHERE table_schema = DATABASE() AND table_name = ? AND partition_name IS NOT NULL',
            ['wdn_events']
        );

        return Cast::int($row['c'] ?? null) > 0;
    }

    /** @return list<string> */
    protected function existingPartitions(): array
    {
        $rows = $this->db->select(
            'SELECT partition_name AS name FROM information_schema.partitions '.
            'WHERE table_schema = DATABASE() AND table_name = ? AND partition_name IS NOT NULL',
            ['wdn_events']
        );

        $names = array_map(fn (mixed $r): string => Cast::str(((array) $r)['name'] ?? null), $rows);

        return array_values(array_filter($names, fn (string $n): bool => $n !== 'pmax'));
    }

    protected function partitionName(Carbon $date): string
    {
        return 'p'.$date->format('Ymd');
    }

    protected function partitionDate(string $name): ?Carbon
    {
        if (! preg_match('/^p(\d{8})$/', $name, $m)) {
            return null;
        }

        $date = Carbon::createFromFormat('Ymd', $m[1]);

        return $date instanceof Carbon ? $date->startOfDay() : null;
    }
}
