<?php

namespace VictorStochero\Warden\Dashboard;

use VictorStochero\Warden\Config\CaptureProfiles;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Support\Cast;

/**
 * Derives a project's capture posture from its stored config — what the parent
 * believes the child will capture — for the dashboard's reduced-capture banner,
 * the lean opt-in notice and the overview indicator. Read-only; no DB writes.
 */
final class CaptureStatus
{
    /** Every recorder Warden ships (the universe the "off" list is measured against). */
    public const ALL_RECORDERS = [
        'request', 'query', 'job', 'exception', 'log', 'mail',
        'notification', 'cache', 'command', 'schedule', 'http', 'user', 'host',
    ];

    /**
     * @return array{
     *     reduced: bool,
     *     off: list<string>,
     *     query_min_ms: int,
     *     request_sample: float,
     *     needs_opt_in: bool,
     *     profile: string|null,
     * }
     */
    public static function forProject(Project $project): array
    {
        $config = is_array($project->config) ? $project->config : [];

        // "off" = event types the project gates off via sample.type_gate. We read
        // ONLY the project's own stored config — never the parent's runtime gate —
        // so an override-less project reads as full (nothing pushed = full). A
        // `recorders` override (the older mechanism) also subtracts from "on".
        $sample = Cast::arr($config['sample'] ?? null);
        $typeGate = Cast::arr($sample['type_gate'] ?? null);

        $off = [];
        foreach (self::ALL_RECORDERS as $type) {
            $gatedOff = array_key_exists($type, $typeGate) && ! Cast::bool($typeGate[$type]);
            $recorderOff = is_array($config['recorders'] ?? null) && ! in_array($type, array_map(fn ($r) => Cast::str($r), $config['recorders']), true);
            if ($gatedOff || $recorderOff) {
                $off[] = $type;
            }
        }

        $queryMin = Cast::int(Cast::arr($config['query'] ?? null)['capture_min_ms'] ?? 0);
        $requestSample = Cast::float(
            Cast::arr(Cast::arr($config['sample'] ?? null)['traces'] ?? null)['request'] ?? 1.0,
            1.0,
        );

        $profile = $project->capture_profile;

        return [
            'reduced' => $off !== [] || $queryMin > 0 || $requestSample < 1.0,
            'off' => $off,
            'query_min_ms' => $queryMin,
            'request_sample' => $requestSample,
            'needs_opt_in' => $profile === null,
            'profile' => $profile,
        ];
    }

    /**
     * Apply the lean profile to a project: seed its config, bump the version so
     * the child picks it up, and mark it lean. Used by the opt-in migration.
     */
    public static function migrateToLean(Project $project): void
    {
        $project->forceFill([
            'config' => CaptureProfiles::lean(),
            'config_version' => Cast::int($project->config_version, 0) + 1,
            'capture_profile' => CaptureProfiles::LEAN,
        ])->save();
    }
}
