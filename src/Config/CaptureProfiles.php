<?php

namespace VictorStochero\Warden\Config;

/**
 * Canonical capture profiles, expressed as a sparse project-config document
 * (the same shape ProjectConfig::sanitize produces and the control plane pushes
 * to the child). The `lean` profile is what a fresh install is seeded with so a
 * new project doesn't bloat the host's database from day one: only high-signal,
 * low-volume recorders are kept, requests are sampled, and queries are captured
 * only above a latency threshold. An operator widens capture from the dashboard.
 *
 * @see ProjectConfig
 * @see KnobMap
 */
final class CaptureProfiles
{
    /** Identifier stored in wdn_projects.capture_profile. */
    public const LEAN = 'lean';

    public const FULL = 'full';

    public const CUSTOM = 'custom';

    /** Event types the lean profile gates off (the multiplicative noise + metadata). */
    public const LEAN_OFF = ['cache', 'http', 'mail', 'notification', 'user', 'command'];

    /**
     * The lean default. Built on the same `sample.type_gate` mechanism the
     * per-project "Captured metrics" UI uses (0.3.4), so a lean project reads
     * back consistently there: keep request (sampled to 20%), query (slow only),
     * job, exception, log, host and schedule; gate off the noisy/metadata types
     * in LEAN_OFF. The shape is a fixed point of ProjectConfig::sanitize.
     *
     * @return array<string, mixed>
     */
    public static function lean(): array
    {
        return [
            'sample' => [
                'traces' => ['request' => 0.2],
                'always_keep' => ['on_exception' => true, 'slower_than_ms' => 1000],
                'type_gate' => array_fill_keys(self::LEAN_OFF, false),
            ],
            'query' => ['capture_min_ms' => 100],
        ];
    }
}
