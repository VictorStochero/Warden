<?php

namespace VictorStochero\Warden\Dashboard;

use Illuminate\Database\Connection;
use VictorStochero\Warden\Support\Cast;

/**
 * Computes a cheap "state version" token for the real-time transport (§5.4). The
 * token changes whenever anything the dashboard cards show for a project changes
 * — a new aggregate rollup, or an issue/incident opening or resolving — so a
 * conditional GET can answer 304 without ever building the heavy KPI payload.
 *
 * Only a handful of indexed MAX/COUNT scalars are read here; the expensive
 * aggregate scan in DashboardRepository runs only when this token has moved.
 */
class StreamCursor
{
    public function __construct(protected Connection $db) {}

    /**
     * A token for the project's live state, scoped by an opaque string (the
     * range) so an ETag minted for one range never spuriously matches another.
     */
    public function forProject(int $projectId, string $scope = ''): string
    {
        // Sums move whenever a rollup changes a value, even an in-place upsert
        // into an existing bucket within the same second — a max(updated_at)
        // alone would miss that (timestamps carry no sub-second precision).
        $aggregates = $this->db->table('wdn_aggregates')
            ->where('project_id', $projectId)
            ->selectRaw('count(*) as c, coalesce(sum(count), 0) as sc, coalesce(sum(sum_duration), 0) as sd, max(updated_at) as u')
            ->first();

        $openIssues = $this->db->table('wdn_issues')
            ->where('project_id', $projectId)
            ->where('status', 'open')
            ->count();

        $openIncidents = $this->db->table('wdn_incidents')
            ->where('project_id', $projectId)
            ->where('status', 'open')
            ->count();

        return $this->hash([
            $scope,
            Cast::int($aggregates->c ?? 0),
            Cast::int($aggregates->sc ?? 0),
            Cast::int($aggregates->sd ?? 0),
            Cast::str($aggregates->u ?? ''),
            $openIssues,
            $openIncidents,
        ]);
    }

    /**
     * @param  array<int, int|string>  $parts
     */
    protected function hash(array $parts): string
    {
        return substr(hash('xxh128', implode('|', $parts)), 0, 16);
    }
}
