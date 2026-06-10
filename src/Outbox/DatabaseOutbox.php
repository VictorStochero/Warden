<?php

namespace VictorStochero\Warden\Outbox;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Json;

/**
 * Default outbox backed by the child's own database (driver "database" needs no
 * extra infrastructure). Implements the high/low-water capture gate: once full,
 * push() is a no-op until the ship daemon drains it below the low mark (§18.6).
 */
class DatabaseOutbox implements Outbox
{
    protected string $table = 'wdn_outbox';

    /** Cached "full" verdict (hysteresis state). */
    protected ?bool $full = null;

    /** In-memory row count, kept exact for this process's own mutations. */
    protected ?int $cachedSize = null;

    /** Monotonic seconds of the last real COUNT resync. */
    protected float $sizeSyncedAt = 0.0;

    public function __construct(
        protected Connection $db,
        protected Repository $config,
    ) {}

    public function push(array $batch): void
    {
        if ($this->isFull()) {
            return; // capture paused — disk safety over completeness
        }

        $now = Carbon::now();

        $this->db->table($this->table)->insert([
            'batch' => Json::encode($batch),
            'attempts' => 0,
            'available_at' => $now,
            'reserved_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($this->cachedSize !== null) {
            $this->cachedSize++;
        }
    }

    public function reserve(int $limit): array
    {
        $now = Carbon::now();

        return $this->db->transaction(function () use ($limit, $now): array {
            // Re-claim rows whose reservation is stale (a crashed shipper) by
            // treating reserved_at older than 60s as available again. The row
            // lock serializes concurrent shippers so no row is handed out twice.
            $rows = $this->db->table($this->table)
                ->where('available_at', '<=', $now)
                ->where(function (Builder $q) use ($now) {
                    $q->whereNull('reserved_at')
                        ->orWhere('reserved_at', '<=', $now->copy()->subSeconds(60));
                })
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                return [];
            }

            $ids = $rows->pluck('id')->all();

            $this->db->table($this->table)
                ->whereIn('id', $ids)
                ->update(['reserved_at' => $now, 'updated_at' => $now]);

            return $rows->map(fn (\stdClass $row): OutboxBatch => new OutboxBatch(
                id: Cast::int($row->id),
                payload: Json::decode($row->batch ?? null),
                attempts: Cast::int($row->attempts),
            ))->all();
        });
    }

    public function delete(OutboxBatch $batch): void
    {
        $this->db->table($this->table)->where('id', $batch->id)->delete();

        if ($this->cachedSize !== null) {
            $this->cachedSize = max(0, $this->cachedSize - 1);
        }
    }

    public function release(OutboxBatch $batch, int $delaySeconds): void
    {
        $this->db->table($this->table)->where('id', $batch->id)->update([
            'attempts' => $batch->attempts + 1,
            'available_at' => Carbon::now()->addSeconds($delaySeconds),
            'reserved_at' => null,
            'updated_at' => Carbon::now(),
        ]);
    }

    public function size(): int
    {
        return $this->db->table($this->table)->count();
    }

    public function isFull(): bool
    {
        $now = $this->monotonic();

        // Resync with a real COUNT at most once per second; between syncs the
        // counter is kept exact by push()/delete(), so the gate is precise for
        // this process without a COUNT on every push.
        if ($this->cachedSize === null || ($now - $this->sizeSyncedAt) >= 1.0) {
            $this->cachedSize = $this->size();
            $this->sizeSyncedAt = $now;
        }

        $high = Cast::int($this->config->get('warden.child.outbox_high_water', 10000), 10000);
        $low = Cast::int($this->config->get('warden.child.outbox_low_water', 8000), 8000);

        $this->full = $this->full === true ? $this->cachedSize > $low : $this->cachedSize >= $high;

        return $this->full;
    }

    protected function monotonic(): float
    {
        return (float) hrtime(true) / 1e9;
    }
}
