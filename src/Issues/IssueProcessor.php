<?php

namespace VictorStochero\Warden\Issues;

use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Cursor;
use VictorStochero\Warden\Support\Json;
use VictorStochero\Warden\Warden;

/**
 * Groups raw exception events into wdn_issues by fingerprint (M6): one row per
 * unique fault, with count, first/last seen, last trace and a single retained
 * stack sample. A resolved issue that recurs is reopened (§15). Incremental via
 * its own cursor and runs suppressed (§18.3).
 */
class IssueProcessor
{
    protected Cursor $cursor;

    public function __construct(
        protected Warden $warden,
        protected Connection $db,
    ) {
        $this->cursor = new Cursor($db);
    }

    public function process(int $projectId): void
    {
        $this->warden->withoutRecording(function () use ($projectId) {
            do {
                $from = $this->cursor->position($projectId, 'issues');

                $events = $this->db->table('wdn_events')
                    ->where('project_id', $projectId)
                    ->where('type', 'exception')
                    ->where('id', '>', $from)
                    ->orderBy('id')
                    ->limit(2000)
                    ->get();

                if ($events->isEmpty()) {
                    return;
                }

                /** @var array<string, array{class:string,message:string,stack:array<array-key,mixed>|null,count:int,users:array<string,bool>,first:string|null,last:string|null,last_trace:string|null}> $groups */
                $groups = [];
                $maxId = $from;

                foreach ($events as $event) {
                    $maxId = max($maxId, Cast::int($event->id));
                    $payload = Json::decode($event->payload);

                    $class = Cast::str($payload['class'] ?? null, 'Exception');
                    $message = Cast::str($payload['message'] ?? null);
                    $stack = is_array($payload['stack'] ?? null) ? $payload['stack'] : null;
                    $fp = Fingerprint::for($class, $message, $stack);

                    $groups[$fp] ??= [
                        'class' => $class, 'message' => $message, 'stack' => $stack,
                        'count' => 0, 'users' => [], 'first' => null, 'last' => null,
                        'last_trace' => null,
                    ];
                    $groups[$fp]['count']++;
                    $occurred = Cast::str($event->occurred_at);
                    $groups[$fp]['first'] = $groups[$fp]['first'] === null ? $occurred : min($groups[$fp]['first'], $occurred);
                    $groups[$fp]['last'] = $groups[$fp]['last'] === null ? $occurred : max($groups[$fp]['last'], $occurred);
                    $groups[$fp]['last_trace'] = Cast::str($event->trace_id ?? null) ?: $groups[$fp]['last_trace'];
                    if (($payload['user_id'] ?? null) !== null) {
                        $groups[$fp]['users'][Cast::str($payload['user_id'])] = true;
                    }
                }

                foreach ($groups as $fp => $group) {
                    $this->upsert($projectId, $fp, $group);
                }

                $this->cursor->advance($projectId, 'issues', $maxId);
            } while ($events->count() === 2000);
        });
    }

    /**
     * @param  array{class:string,message:string,stack:array<array-key,mixed>|null,count:int,users:array<string,bool>,first:string|null,last:string|null,last_trace:string|null}  $group
     */
    protected function upsert(int $projectId, string $fingerprint, array $group): void
    {
        $this->db->transaction(function () use ($projectId, $fingerprint, $group) {
            $existing = $this->db->table('wdn_issues')
                ->where('project_id', $projectId)
                ->where('fingerprint', $fingerprint)
                ->lockForUpdate()
                ->first();

            $now = Carbon::now();
            $newUsers = count($group['users']);

            if ($existing === null) {
                $this->db->table('wdn_issues')->insert([
                    'project_id' => $projectId,
                    'fingerprint' => $fingerprint,
                    'class' => $group['class'],
                    'message' => $group['message'],
                    'last_trace_id' => $group['last_trace'],
                    'count' => $group['count'],
                    'users_affected' => $newUsers,
                    'first_seen_at' => $group['first'],
                    'last_seen_at' => $group['last'],
                    'status' => 'open',
                    'stack' => Json::encode($group['stack']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return;
            }

            $this->db->table('wdn_issues')->where('id', $existing->id)->update([
                'count' => Cast::int($existing->count) + $group['count'],
                'users_affected' => Cast::int($existing->users_affected) + $newUsers,
                'last_trace_id' => $group['last_trace'] ?? $existing->last_trace_id,
                'last_seen_at' => max(Cast::str($existing->last_seen_at), Cast::str($group['last'])),
                // Recurrence reopens a resolved issue; ignored stays ignored.
                'status' => $existing->status === 'resolved' ? 'open' : $existing->status,
                'updated_at' => $now,
            ]);
        });
    }
}
