<?php

namespace VictorStochero\Warden\Ingestion;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Json;
use VictorStochero\Warden\Warden;

/**
 * Parent-side persistence of raw events (M4). Bulk-inserts into wdn_events,
 * deriving the partition key (occurred_date) and stamping received_at to absorb
 * clock skew between children (§18.7). Runs suppressed so the parent never
 * observes its own ingest writes (§18.3).
 */
class DatabaseIngestor implements Ingestor
{
    public function __construct(
        protected Warden $observer,
        protected Connection $db,
    ) {}

    public function ingest(string $project, array $batches): int
    {
        $projectModel = Project::query()->where('slug', $project)->first();

        if ($projectModel === null) {
            return 0;
        }

        return $this->observer->withoutRecording(function () use ($projectModel, $batches): int {
            $now = Carbon::now('UTC');
            $accepted = 0;

            foreach ($batches as $batch) {
                if (! is_array($batch)) {
                    continue;
                }

                $batchId = isset($batch['id']) ? Cast::str($batch['id']) : '';
                $events = isset($batch['events']) && is_array($batch['events'])
                    ? array_values(array_filter($batch['events'], 'is_array'))
                    : [];

                if ($batchId !== '' && ! $this->claimBatch(Cast::int($projectModel->id), $batchId, $now)) {
                    continue; // duplicate delivery — already ingested
                }

                $this->insertEvents(Cast::int($projectModel->id), $events, $now);
                $accepted += count($events);
            }

            $projectModel->forceFill(['last_seen_at' => $now])->save();

            return $accepted;
        });
    }

    /** Returns false if this (project, batch_id) was already recorded. */
    protected function claimBatch(int $projectId, string $batchId, Carbon $now): bool
    {
        try {
            $this->db->table('wdn_ingested_batches')->insert([
                'project_id' => $projectId,
                'batch_id' => $batchId,
                'received_at' => $now,
            ]);

            return true;
        } catch (QueryException) {
            return false;
        }
    }

    /** @param array<int, mixed> $events */
    protected function insertEvents(int $projectId, array $events, Carbon $now): void
    {
        $rows = [];

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $occurredAt = $this->parseTime($event['occurred_at'] ?? null) ?? $now;

            $rows[] = [
                'project_id' => $projectId,
                'type' => Cast::str($event['type'] ?? null, 'unknown'),
                'trace_id' => $event['trace_id'] ?? null,
                'span_id' => $event['span_id'] ?? null,
                'parent_span_id' => $event['parent_span_id'] ?? null,
                'occurred_at' => $occurredAt,
                'occurred_date' => $occurredAt->toDateString(),
                'received_at' => $now,
                'duration_us' => isset($event['duration_us']) ? Cast::int($event['duration_us']) : null,
                'payload' => isset($event['payload']) ? Json::encode($event['payload']) : null,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->db->table('wdn_events')->insert($chunk);
        }
    }

    protected function parseTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            // Children stamp occurred_at as a naive UTC instant (Warden::microNow);
            // parse it as UTC so it is stored and compared consistently (§timezone).
            return Carbon::parse($value, 'UTC');
        } catch (\Throwable) {
            return null;
        }
    }
}
