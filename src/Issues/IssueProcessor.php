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

                /** @var array<string, array{class:string,message:string,stack:array<array-key,mixed>|null,count:int,users:array<string,bool>,first:string|null,last:string|null,last_trace:string|null,last_release:string|null}> $groups */
                $groups = [];
                $maxId = $from;

                foreach ($events as $event) {
                    $maxId = max($maxId, Cast::int($event->id));
                    $payload = Json::decode($event->payload);

                    $class = Cast::str($payload['class'] ?? null, 'Exception');
                    $message = Cast::str($payload['message'] ?? null);
                    $stack = is_array($payload['stack'] ?? null) ? $payload['stack'] : null;
                    $fp = Fingerprint::forPayload($payload);

                    $groups[$fp] ??= [
                        'class' => $class, 'message' => $message, 'stack' => $stack,
                        'count' => 0, 'users' => [], 'first' => null, 'last' => null,
                        'last_trace' => null, 'last_release' => null,
                    ];
                    $groups[$fp]['count']++;
                    $occurred = Cast::str($event->occurred_at);
                    $groups[$fp]['first'] = $groups[$fp]['first'] === null ? $occurred : min($groups[$fp]['first'], $occurred);
                    $groups[$fp]['last'] = $groups[$fp]['last'] === null ? $occurred : max($groups[$fp]['last'], $occurred);
                    $groups[$fp]['last_trace'] = Cast::str($event->trace_id ?? null) ?: $groups[$fp]['last_trace'];
                    // Events arrive ordered by id, so the last non-empty release
                    // wins — the release of the most recent occurrence.
                    $groups[$fp]['last_release'] = Cast::str($event->release ?? null) ?: $groups[$fp]['last_release'];
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
     * @param  array{class:string,message:string,stack:array<array-key,mixed>|null,count:int,users:array<string,bool>,first:string|null,last:string|null,last_trace:string|null,last_release:string|null}  $group
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
                    'last_release' => $group['last_release'],
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
                'last_release' => $group['last_release'] ?? $existing->last_release,
                // Deploy-aware recurrence (§5.6): a resolved issue reopens only when
                // it returns on a release other than the one it was resolved on (a
                // real regression). No release info → fall back to reopen-on-recurrence.
                // Ignored stays ignored.
                'status' => $this->recurrenceStatus($existing, $group['last_release']),
                'updated_at' => $now,
            ]);
        });
    }

    /**
     * Status of a recurring issue. A resolved issue reopens only on a release
     * other than the one it was resolved on (a regression after a deploy); with
     * no release info it falls back to the historical reopen-on-recurrence. An
     * open issue stays open and an ignored one stays ignored.
     */
    protected function recurrenceStatus(\stdClass $existing, ?string $newRelease): string
    {
        $status = Cast::str($existing->status);

        if ($status !== 'resolved') {
            return $status;
        }

        $resolvedRelease = $existing->resolved_release !== null ? Cast::str($existing->resolved_release) : null;

        return ($resolvedRelease === null || $newRelease !== $resolvedRelease) ? 'open' : 'resolved';
    }
}
