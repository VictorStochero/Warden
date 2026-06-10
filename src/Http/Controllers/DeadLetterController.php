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

        DeadLetter::create([
            'project_id' => $project->id,
            'batch_id' => isset($data['batch_id']) ? Cast::str($data['batch_id']) : null,
            'reason' => isset($data['reason']) ? mb_substr(Cast::str($data['reason']), 0, 255) : null,
            'attempts' => Cast::int($data['attempts'] ?? 0),
            'reported_at' => Carbon::now(),
        ]);

        return response()->json(['recorded' => true], 202);
    }
}
