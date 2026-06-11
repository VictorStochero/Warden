<?php

namespace VictorStochero\Warden\Config;

/**
 * Single source of truth for the child-behaviour knobs the parent may override.
 * Maps each knob's dotted config path (under warden.child) to its .env variable
 * (or null when the knob has no env var and the parent may always set it).
 * Drives parent-side validation, the child's .env-precedence check, and the UI.
 */
final class KnobMap
{
    /** @return array<string, string|null> knob => env var (null = no env) */
    public static function all(): array
    {
        return [
            'recorders' => null,
            'sample.traces.request' => 'WARDEN_SAMPLE_REQUEST',
            'sample.traces.job' => 'WARDEN_SAMPLE_JOB',
            'sample.traces.command' => null,
            'sample.traces.schedule' => null,
            'sample.always_keep.on_exception' => null,
            'sample.always_keep.slower_than_ms' => 'WARDEN_ALWAYS_KEEP_MS',
            'sample.type_gate' => null,
            'scrub' => null,
            'capture.pii' => 'WARDEN_CAPTURE_PII',
            'capture.mail_body' => 'WARDEN_CAPTURE_MAIL_BODY',
            'capture.disable_credential_scrub' => 'WARDEN_DISABLE_CREDENTIAL_SCRUB',
            'host_interval' => 'WARDEN_HOST_INTERVAL',
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function envVar(string $knob): ?string
    {
        return self::all()[$knob] ?? null;
    }
}
