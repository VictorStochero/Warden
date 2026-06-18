<?php

namespace VictorStochero\Warden\Ingestion;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Throwable;
use VictorStochero\Warden\Bridge\NullEventForwarder;
use VictorStochero\Warden\Contracts\EventForwarder;
use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Events\EventsIngested;
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
        protected ?EventForwarder $forwarder = null,
        protected ?Dispatcher $events = null,
    ) {}

    public function ingest(string $project, array $batches): int
    {
        $projectModel = Project::query()->where('slug', $project)->first();

        if ($projectModel === null) {
            return 0;
        }

        /** @var list<array<array-key, mixed>> $forwarded */
        $forwarded = [];

        $accepted = $this->observer->withoutRecording(function () use ($projectModel, $batches, &$forwarded): int {
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

                // Defense-in-depth type gate: drop events whose type is disabled
                // for this project before they ever reach wdn_events, so a stale
                // child can't bloat the parent DB (RNF-1/§per-project metrics).
                // accepted still counts the received events — the drop is parent
                // policy, not a rejection, so the child sees a full acceptance.
                $kept = $this->capturableEvents($projectModel, $events);

                $this->insertEvents(Cast::int($projectModel->id), $kept, $now);
                $accepted += count($events);

                foreach ($kept as $event) {
                    $forwarded[] = $event;
                }
            }

            $projectModel->forceFill(['last_seen_at' => $now])->save();

            return $accepted;
        });

        // §9.2 Bridge seam: hand the persisted events to the configured forwarder
        // and fire the Laravel event. Best-effort + suppressed — a forwarder that
        // throws (or no listeners) must never break the ingest (RNF-2).
        if ($forwarded !== []) {
            $this->forward(Cast::str($projectModel->slug), $forwarded);
        }

        return $accepted;
    }

    /** @param list<array<array-key, mixed>> $events */
    protected function forward(string $projectSlug, array $events): void
    {
        $this->observer->withoutRecording(function () use ($projectSlug, $events): void {
            try {
                ($this->forwarder ?? new NullEventForwarder)->forward($projectSlug, $events);
                $this->events?->dispatch(new EventsIngested($projectSlug, $events));
            } catch (Throwable) {
                // Best-effort: the Bridge seam must never break the ingest.
            }
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

    /**
     * Keep only events whose type is not gated off for this project. A type is
     * dropped only when its type_gate value is strictly false — fractional
     * sampling is the child's job and is never re-applied here (decision: drop
     * only on explicit off).
     *
     * @param  array<int, mixed>  $events
     * @return list<array<array-key, mixed>>
     */
    protected function capturableEvents(Project $project, array $events): array
    {
        $disabled = $this->disabledTypes($project);

        $out = [];
        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }
            if ($disabled !== [] && isset($disabled[Cast::str($event['type'] ?? null, 'unknown')])) {
                continue;
            }
            $out[] = $event;
        }

        return $out;
    }

    /**
     * Event types gated off for this project (type_gate === false). Fail-open:
     * a missing/malformed config means "nothing disabled", so capture is never
     * broken by a bad config document (RNF-2).
     *
     * @return array<string, true>
     */
    protected function disabledTypes(Project $project): array
    {
        $config = is_array($project->config) ? $project->config : [];
        $sample = is_array($config['sample'] ?? null) ? $config['sample'] : [];
        $gate = $sample['type_gate'] ?? null;

        if (! is_array($gate)) {
            return [];
        }

        $disabled = [];
        foreach ($gate as $type => $on) {
            if ($on === false) {
                $disabled[Cast::str($type)] = true;
            }
        }

        return $disabled;
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

            $release = isset($event['release']) ? trim(Cast::str($event['release'])) : '';

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
                'release' => $release !== '' ? $release : null,
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
        } catch (Throwable) {
            return null;
        }
    }
}
