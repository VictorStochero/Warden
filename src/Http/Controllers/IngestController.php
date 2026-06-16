<?php

namespace VictorStochero\Warden\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Schedule\AuditSchedule;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Compression;
use VictorStochero\Warden\Transport\Signer;
use VictorStochero\Warden\Warden;

/**
 * Parent ingestion endpoint (M4). Authenticates by per-project token, verifies
 * the HMAC over the raw body, rejects replays outside the skew window, then
 * hands raw events to the Ingestor. Rate limiting is applied at the route.
 */
class IngestController
{
    public function __invoke(Request $request, Ingestor $ingestor, Warden $observer): JsonResponse
    {
        // Optional TLS enforcement: reject plaintext ingest before any work. The
        // request honours trusted-proxy headers configured by the host app, so a
        // TLS-terminating proxy that sets X-Forwarded-Proto is respected.
        if (Cast::bool(config('warden.parent.require_https')) && ! $request->isSecure()) {
            return response()->json(['error' => 'https_required'], 403);
        }

        $token = (string) $request->header('X-Warden-Token', '');
        $signature = (string) $request->header('X-Warden-Signature', '');
        $raw = (string) $request->getContent();

        $maxBytes = Cast::int(config('warden.parent.max_body_bytes', 1048576), 1048576);

        // The wire body (compressed or not) must itself fit before we inflate.
        if (strlen($raw) > $maxBytes) {
            return response()->json(['error' => 'payload_too_large'], 413);
        }

        // Inflate a gzip body before HMAC/JSON — the child signs the uncompressed
        // JSON, so verification always runs against the decompressed bytes.
        $body = strtolower((string) $request->header('Content-Encoding')) === 'gzip'
            ? Compression::inflate($raw, $maxBytes)
            : $raw;

        if ($body === null) {
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

        if (Cast::int($data['schema_version'] ?? 0) !== 2) {
            return response()->json(['error' => 'unsupported_schema'], 422);
        }

        if (! isset($data['batches']) || ! is_array($data['batches'])) {
            return response()->json(['error' => 'malformed'], 422);
        }

        // Anti-replay: reject bodies whose timestamp is outside the skew window.
        $skew = Cast::int(config('warden.parent.max_skew', 300), 300);
        $sentAt = Cast::int($data['sent_at'] ?? 0);

        if ($sentAt === 0 || abs(Carbon::now()->getTimestamp() - $sentAt) > $skew) {
            return response()->json(['error' => 'stale'], 422);
        }

        // The signed payload may name its project; if so it must match the token.
        if (isset($data['project']) && $data['project'] !== $project->slug) {
            return response()->json(['error' => 'project_mismatch'], 422);
        }

        $batches = array_values(array_filter($data['batches'], 'is_array'));

        $maxEvents = Cast::int(config('warden.parent.max_events_per_request', 5000), 5000);
        $total = 0;
        foreach ($batches as $b) {
            $evs = $b['events'] ?? [];
            $total += is_array($evs) ? count($evs) : 0;
        }
        if ($total > $maxEvents) {
            return response()->json(['error' => 'too_many_events'], 413);
        }

        $accepted = $ingestor->ingest($project->slug, $batches);

        // Derive the project's display timezone from the child's reported
        // app.timezone — replaces the manual selector. Suppressed so the
        // write is never self-observed (§18.3).
        $observer->withoutRecording(fn () => $this->syncTimezone($project, $data));

        // Record which knobs the child reports as pinned by its own .env, so the
        // dashboard can flag overridden toggles. Best-effort and suppressed: a
        // failure here must never break the ingest (RNF-2).
        $observer->withoutRecording(fn () => $this->syncEnvOverrides($project, $data));

        // Control channel: tell the child to run a dependency audit when the
        // parent-configured interval has elapsed (M: parent-driven scheduling),
        // plus the sparse config push via a version handshake.
        $childVersion = Cast::int($data['config_version'] ?? 0);
        $projectVersion = Cast::int($project->config_version, 0);

        $payload = [
            'accepted' => $accepted,
            'audit_due' => $this->auditDue($project),
            'config_version' => $projectVersion,
        ];

        // Only resend the document when the child is stale — saves payload.
        if ($childVersion !== $projectVersion) {
            $payload['config'] = is_array($project->config) ? $project->config : [];
        }

        return response()->json($payload, 202);
    }

    /**
     * Auto-detect the project's display timezone from the app.timezone the child
     * reports in its ingest body. Only a valid IANA identifier that differs from
     * the stored value is written, so the column self-heals when the child's
     * config changes and a no-op report never touches the row.
     *
     * @param  array<array-key, mixed>  $data
     */
    protected function syncTimezone(Project $project, array $data): void
    {
        $tz = Cast::str($data['app_timezone'] ?? '');

        if ($tz === '' || $tz === Cast::str($project->timezone) || ! in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            return;
        }

        $project->forceFill(['timezone' => $tz])->save();
    }

    /**
     * Persist the .env-pinned knob list the child reports (env_overrides). Only
     * non-empty strings survive. The row is touched only when the list actually
     * changes so a steady stream of identical reports never writes. Best-effort:
     * any failure is swallowed so it can never break the ingest (RNF-2).
     *
     * @param  array<array-key, mixed>  $data
     */
    protected function syncEnvOverrides(Project $project, array $data): void
    {
        try {
            $overrides = array_values(array_filter(
                Cast::arr($data['env_overrides'] ?? []),
                fn ($v) => is_string($v) && $v !== '',
            ));

            if ($overrides === ($project->env_overrides ?? [])) {
                return;
            }

            $project->forceFill(['env_overrides' => $overrides])->save();
        } catch (\Throwable) {
            // resilience: never let override bookkeeping break the ingest
        }
    }

    /**
     * Whether the project is due for a dependency audit:
     *  - an explicit "run now" request that no snapshot has answered yet, or
     *  - the configured schedule (daily/weekly/monthly at audit_day/audit_hour,
     *    in the project's timezone) has fired since the last `security` event.
     */
    protected function auditDue(Project $project): bool
    {
        return (new AuditSchedule)->due($project);
    }
}
