<?php

namespace VictorStochero\Warden\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
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

            $deadLetterCutoff = Carbon::now()->subDays(Cast::int(config('warden.parent.dead_letter_retention_days', 30), 30));
            $deadLettersRemoved = Schema::db()->table('wdn_dead_letter')
                ->where('reported_at', '<', $deadLetterCutoff)
                ->delete();
            $this->components->twoColumnDetail('Dead letters deleted', (string) $deadLettersRemoved);

            $this->pruneByProject();
        });

        return self::SUCCESS;
    }

    /**
     * Per-project retention (§5.12): a project may keep raw events / aggregates
     * for FEWER days than the global window. Run after the global prune as an
     * extra scoped DELETE — the override only tightens below the global ceiling
     * (extending past it isn't possible once the global prune dropped the rows).
     */
    private function pruneByProject(): void
    {
        $projects = Schema::db()->table('wdn_projects')
            ->where(function (Builder $q): void {
                $q->whereNotNull('raw_retention_days')->orWhereNotNull('aggregate_retention_days');
            })
            ->get(['id', 'raw_retention_days', 'aggregate_retention_days']);

        $rawRemoved = 0;
        $aggRemoved = 0;

        foreach ($projects as $project) {
            $projectId = Cast::int($project->id);

            $rawDays = $project->raw_retention_days;
            if ($rawDays !== null) {
                $cutoff = Carbon::now()->subDays(Cast::int($rawDays));
                $rawRemoved += Schema::db()->table('wdn_events')
                    ->where('project_id', $projectId)
                    ->where('occurred_at', '<', $cutoff)
                    ->delete();
            }

            $aggDays = $project->aggregate_retention_days;
            if ($aggDays !== null) {
                $cutoff = Carbon::now()->subDays(Cast::int($aggDays));
                $aggRemoved += Schema::db()->table('wdn_aggregates')
                    ->where('project_id', $projectId)
                    ->where('bucket', '<', $cutoff)
                    ->delete();
            }
        }

        if ($projects->isNotEmpty()) {
            $this->components->twoColumnDetail('Per-project raw events deleted', (string) $rawRemoved);
            $this->components->twoColumnDetail('Per-project aggregates deleted', (string) $aggRemoved);
        }
    }
}
