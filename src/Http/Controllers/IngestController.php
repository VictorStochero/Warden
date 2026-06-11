<?php

namespace VictorStochero\Warden\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Support\Cast;
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
        $body = $request->getContent();

        $maxBytes = Cast::int(config('warden.parent.max_body_bytes', 1048576), 1048576);
        if (strlen((string) $body) > $maxBytes) {
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
     * Whether the project is due for a dependency audit:
     *  - an explicit "run now" request that no snapshot has answered yet, or
     *  - the configured schedule (daily/weekly/monthly at audit_day/audit_hour,
     *    in the project's timezone) has fired since the last `security` event.
     */
    protected function auditDue(Project $project): bool
    {
        $conn = config('warden.connection');
        $db = DB::connection(is_string($conn) && $conn !== '' ? $conn : null);

        $last = $db->table('wdn_events')
            ->where('project_id', $project->id)
            ->where('type', 'security')
            ->max('received_at');
        $lastTs = $last !== null ? Carbon::parse(Cast::str($last)) : null;

        // Instant request: due until a snapshot newer than the request arrives.
        $requested = $project->audit_requested_at;
        if ($requested !== null && ($lastTs === null || $lastTs->lt($requested))) {
            return true;
        }

        $frequency = Cast::str($project->audit_frequency, 'off');
        if ($frequency === 'off') {
            return false;
        }

        $boundary = $this->lastScheduledBoundary($project, $frequency);
        if ($boundary === null) {
            return false;
        }

        // Due when no audit has landed since the most recent scheduled boundary.
        return $lastTs === null || $lastTs->lt($boundary);
    }

    /**
     * The most recent moment the schedule should have fired, as a UTC instant.
     * Computed in the project's timezone from audit_day/audit_hour. Null when
     * the next-occurrence math cannot be satisfied (e.g. an unknown frequency).
     */
    protected function lastScheduledBoundary(Project $project, string $frequency): ?Carbon
    {
        $tz = Cast::str($project->timezone) ?: Cast::str(config('app.timezone'), 'UTC');
        $hour = $project->audit_hour !== null ? Cast::int($project->audit_hour) : 0;
        $now = Carbon::now($tz);

        $candidate = match ($frequency) {
            'daily' => $now->copy()->setTime($hour, 0),
            'weekly' => $this->weeklyBoundary($now, $project, $hour),
            'monthly' => $this->monthlyBoundary($now, $project, $hour),
            default => null,
        };

        if ($candidate === null) {
            return null;
        }

        // If today's/this period's slot hasn't arrived yet, step back one period.
        if ($candidate->gt($now)) {
            $candidate = match ($frequency) {
                'daily' => $candidate->subDay(),
                'weekly' => $candidate->subWeek(),
                'monthly' => $candidate->subMonthNoOverflow(),
                default => $candidate,
            };
        }

        return $candidate->utc();
    }

    /** Weekly slot: the configured weekday (0=Sun..6=Sat) at the chosen hour. */
    protected function weeklyBoundary(Carbon $now, Project $project, int $hour): Carbon
    {
        $weekday = $project->audit_day !== null ? Cast::int($project->audit_day) : $now->dayOfWeek;
        $weekday = max(0, min(6, $weekday));

        $slot = $now->copy()->setTime($hour, 0);
        $diff = ($slot->dayOfWeek - $weekday + 7) % 7;

        return $slot->subDays($diff);
    }

    /** Monthly slot: the configured day-of-month (1-31, clamped) at the hour. */
    protected function monthlyBoundary(Carbon $now, Project $project, int $hour): Carbon
    {
        $day = $project->audit_day !== null ? Cast::int($project->audit_day) : 1;
        $day = max(1, min(31, $day));
        $day = min($day, $now->daysInMonth);

        return $now->copy()->setDate($now->year, $now->month, $day)->setTime($hour, 0);
    }
}
