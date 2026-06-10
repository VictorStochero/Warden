<?php

namespace VictorStochero\Warden\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use VictorStochero\Warden\Schema\SchemaManager;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Schema;
use VictorStochero\Warden\Warden;

/**
 * Applies retention (M9). Raw events use DROP PARTITION where supported and
 * fall back to chunked DELETE otherwise; aggregates are small and always pruned
 * by DELETE on the bucket (§18.5).
 */
class PruneCommand extends Command
{
    protected $signature = 'warden:prune';

    protected $description = 'Apply retention: drop old raw events and aggregates (parent)';

    public function handle(SchemaManager $schema, Warden $warden): int
    {
        if (config('warden.mode') !== 'parent') {
            $this->components->error('warden:prune only runs in parent mode.');

            return self::FAILURE;
        }

        $warden->withoutRecording(function () use ($schema) {
            $rawCutoff = Carbon::now()->subDays(Cast::int(config('warden.parent.raw_retention_days', 7), 7));
            $aggCutoff = Carbon::now()->subDays(Cast::int(config('warden.parent.aggregate_retention_days', 90), 90));

            $rawRemoved = $schema->pruneOlderThan($rawCutoff);
            $this->components->twoColumnDetail(
                $schema->prunesByPartition() ? 'Raw partitions dropped' : 'Raw events deleted',
                (string) $rawRemoved
            );

            $aggRemoved = Schema::db()->table('wdn_aggregates')->where('bucket', '<', $aggCutoff)->delete();
            $this->components->twoColumnDetail('Aggregates deleted', (string) $aggRemoved);

            // Resolved/ignored issues that have gone quiet can also be reclaimed.
            $issuesRemoved = Schema::db()->table('wdn_issues')
                ->whereIn('status', ['resolved', 'ignored'])
                ->where('last_seen_at', '<', $aggCutoff)
                ->delete();
            $this->components->twoColumnDetail('Stale issues deleted', (string) $issuesRemoved);

            $batchesRemoved = Schema::db()->table('wdn_ingested_batches')
                ->where('received_at', '<', $rawCutoff)
                ->delete();
            $this->components->twoColumnDetail('Ingested batches deleted', (string) $batchesRemoved);
        });

        return self::SUCCESS;
    }
}
