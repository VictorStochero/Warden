<?php

namespace VictorStochero\Warden\Schedule;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Support\Cast;

/**
 * Decides whether a project's dependency audit is due, from its configurable
 * schedule (off / daily / weekly / monthly + day + hour, in the project's
 * timezone) plus the instant "run now" request. Single source of truth shared
 * by the parent advertising audit-due to a child on the ingest response and by
 * a self-monitoring parent scheduling its own `warden:audit`.
 */
class AuditSchedule
{
    public function due(Project $project): bool
    {
        $conn = config('warden.connection');
        $db = DB::connection(is_string($conn) && $conn !== '' ? $conn : null);

        $last = $db->table('wdn_events')
            ->where('project_id', $project->id)
            ->where('type', 'security')
            ->max('received_at');
        $lastTs = $last !== null ? Carbon::parse(Cast::str($last), 'UTC') : null;

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
