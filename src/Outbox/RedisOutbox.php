<?php

namespace VictorStochero\Warden\Outbox;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Ids;
use VictorStochero\Warden\Support\Json;

/**
 * Optional Redis-backed outbox (an accelerator, never a requirement). Pending
 * batches live in a sorted set scored by availability; reserved batches move to
 * a processing set with a visibility timeout so a crashed shipper's work is
 * reclaimed. Honours the same high/low-water capture gate as the DB outbox.
 *
 * All operations go through the typed Redis command() API rather than the
 * connection's magic methods.
 */
class RedisOutbox implements Outbox
{
    protected string $pending = 'warden:outbox:pending';

    protected string $processing = 'warden:outbox:processing';

    protected ?bool $full = null;

    public function __construct(
        protected RedisFactory $redis,
        protected Repository $config,
    ) {}

    public function push(array $batch): void
    {
        if ($this->isFull()) {
            return;
        }

        $member = Json::encode(['id' => Ids::generate(), 'attempts' => 0, 'payload' => $batch]);

        $this->conn()->command('zadd', [$this->pending, time(), $member]);
    }

    public function reserve(int $limit): array
    {
        $now = time();
        $conn = $this->conn();

        // Reclaim expired reservations back into pending.
        foreach (Cast::arr($conn->command('zrangebyscore', [$this->processing, 0, $now])) as $stale) {
            $member = Cast::str($stale);
            $conn->command('zrem', [$this->processing, $member]);
            $conn->command('zadd', [$this->pending, $now, $member]);
        }

        $members = Cast::arr($conn->command('zrangebyscore', [$this->pending, 0, $now, ['limit' => [0, $limit]]]));
        $reserved = [];

        foreach ($members as $raw) {
            $member = Cast::str($raw);

            if (Cast::int($conn->command('zrem', [$this->pending, $member])) === 0) {
                continue; // claimed by another shipper
            }

            $conn->command('zadd', [$this->processing, $now + 60, $member]);
            $data = Json::decode($member);

            $reserved[] = new OutboxBatch(
                id: $member, // the raw member is the handle
                payload: Cast::arr($data['payload'] ?? null),
                attempts: Cast::int($data['attempts'] ?? null),
            );
        }

        return $reserved;
    }

    public function delete(OutboxBatch $batch): void
    {
        $this->conn()->command('zrem', [$this->processing, $batch->id]);
        $this->full = null;
    }

    public function release(OutboxBatch $batch, int $delaySeconds): void
    {
        $conn = $this->conn();
        $conn->command('zrem', [$this->processing, $batch->id]);

        $data = Json::decode(Cast::str($batch->id));
        $data['attempts'] = $batch->attempts + 1;

        $conn->command('zadd', [$this->pending, time() + $delaySeconds, Json::encode($data)]);
    }

    public function size(): int
    {
        return Cast::int($this->conn()->command('zcard', [$this->pending]));
    }

    public function isFull(): bool
    {
        $high = Cast::int($this->config->get('warden.child.outbox_high_water', 10000), 10000);
        $low = Cast::int($this->config->get('warden.child.outbox_low_water', 8000), 8000);
        $size = $this->size();

        $this->full = $this->full === true ? $size > $low : $size >= $high;

        return $this->full;
    }

    protected function conn(): Connection
    {
        return $this->redis->connection();
    }
}
