<?php

namespace VictorStochero\Warden\Console;

use Illuminate\Console\Command;
use VictorStochero\Warden\Schema\SchemaManager;
use VictorStochero\Warden\Support\Cast;

class PartitionCommand extends Command
{
    protected $signature = 'warden:partition
        {--days= : How many days of partitions to pre-create}';

    protected $description = 'Ensure wdn_events is partitioned and pre-create upcoming partitions (parent)';

    public function handle(SchemaManager $schema): int
    {
        if (config('warden.mode') !== 'parent') {
            $this->components->error('warden:partition only runs in parent mode.');

            return self::FAILURE;
        }

        if (! $schema->prunesByPartition() && ! config('warden.parent.partitioning')) {
            $this->components->info('Partitioning disabled or unsupported on this driver — nothing to do.');

            return self::SUCCESS;
        }

        $schema->ensurePartitioned();

        $days = Cast::int($this->option('days') ?: config('warden.parent.partition_ahead_days', 7), 7);
        $schema->createPartitionsAhead($days);

        $this->components->info($schema->prunesByPartition()
            ? "wdn_events partitioned; {$days} days of partitions ensured."
            : 'Driver does not support partitioning; using DELETE-based pruning.');

        return self::SUCCESS;
    }
}
