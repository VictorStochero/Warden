<?php

namespace VictorStochero\Warden\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use VictorStochero\Warden\Models\DeadLetter;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Transport\Signer;

/**
 * Receives dead-letter reports from children when a batch is dropped after
 * exhausting retries. Authenticated with the same per-project HMAC as ingest.
 * Centralizes shipping-failure visibility on the parent (dashboard).
 */
class DeadLetterController
{
    public function __invoke(Request $request): JsonResponse
    {
        if (Cast::bool(config('warden.parent.require_https')) && ! $request->isSecure()) {
            return response()->json(['error' => 'https_required'], 403);
        }

        $token = (string) $request->header('X-Warden-Token', '');
        $signature = (string) $request->header('X-Warden-Signature', '');
        $body = (string) $request->getContent();

        // Same payload guard as ingest (#7): refuse oversized bodies before any
        // signature/JSON work so a child can't exhaust memory with a giant report.
        $maxBytes = Cast::int(config('warden.parent.max_body_bytes', 1048576), 1048576);
        if (strlen($body) > $maxBytes) {
            return response()->json(['error' => 'payload_too_large'], 413);
        }

        $project = Project::query()->where('token', $token)->where('active', true)->first();

        if ($project === null || $token === '') {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        if (! (new Signer((string) $project->secret))->verify($body, $signature)) {
            return response()->json(['error' => 'bad_signature'], 401);
        }

        $data = json_decode($body, true);

        if (! is_array($data)) {
            return response()->json(['error' => 'malformed'], 422);
        }

        // Anti-replay: reject reports whose timestamp is outside the skew window,
        // same guard as ingest — a captured dead-letter body can't be replayed.
        $skew = Cast::int(config('warden.parent.max_skew', 300), 300);
        $sentAt = Cast::int($data['sent_at'] ?? 0);

        if ($sentAt === 0 || abs(Carbon::now()->getTimestamp() - $sentAt) > $skew) {
            return response()->json(['error' => 'stale'], 422);
        }

        // Cap to the column width (string(64)) so an oversized batch_id can't
        // raise a QueryException on strict-mode MySQL / Postgres.
        $batchId = isset($data['batch_id']) ? mb_substr(Cast::str($data['batch_id']), 0, 64) : null;

        $attributes = [
            'reason' => isset($data['reason']) ? mb_substr(Cast::str($data['reason']), 0, 255) : null,
            'attempts' => Cast::int($data['attempts'] ?? 0),
            'reported_at' => Carbon::now(),
        ];

        // Idempotent on (project_id, batch_id): a retried report for the same
        // batch refreshes the row instead of piling up duplicates. Reports with
        // no batch_id are appended (nothing to dedupe on).
        if ($batchId !== null && $batchId !== '') {
            DeadLetter::query()->updateOrCreate(
                ['project_id' => $project->id, 'batch_id' => $batchId],
                $attributes,
            );
        } else {
            DeadLetter::create(['project_id' => $project->id, 'batch_id' => null] + $attributes);
        }

        return response()->json(['recorded' => true], 202);
    }
}
