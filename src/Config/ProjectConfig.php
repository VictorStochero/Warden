<?php

namespace VictorStochero\Warden\Config;

use VictorStochero\Warden\Support\Cast;

/**
 * Parent-side validation/normalisation of a project's sparse config document.
 * Only known knobs survive; values are coerced and clamped. The output is what
 * gets stored in wdn_projects.config and pushed verbatim to the child.
 */
final class ProjectConfig
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function sanitize(array $input): array
    {
        $out = [];

        if (array_key_exists('recorders', $input) && is_array($input['recorders'])) {
            $out['recorders'] = array_values(array_filter(
                array_map(fn ($r) => Cast::str($r), $input['recorders']),
                fn (string $r) => $r !== '',
            ));
        }

        $sample = Cast::arr($input['sample'] ?? null);
        $traces = Cast::arr($sample['traces'] ?? null);

        /** @var array<string, mixed> $outSample */
        $outSample = [];
        /** @var array<string, float> $outTraces */
        $outTraces = [];

        foreach (['request', 'job', 'command', 'schedule'] as $k) {
            $v = $traces[$k] ?? null;
            if ($v !== null) {
                $outTraces[$k] = max(0.0, min(1.0, Cast::float($v)));
            }
        }
        if ($outTraces !== []) {
            $outSample['traces'] = $outTraces;
        }

        $alwaysKeep = Cast::arr($sample['always_keep'] ?? null);
        /** @var array<string, mixed> $outAlwaysKeep */
        $outAlwaysKeep = [];

        if (array_key_exists('on_exception', $alwaysKeep)) {
            $outAlwaysKeep['on_exception'] = Cast::bool($alwaysKeep['on_exception']);
        }
        if (array_key_exists('slower_than_ms', $alwaysKeep)) {
            $outAlwaysKeep['slower_than_ms'] = max(0, Cast::int($alwaysKeep['slower_than_ms']));
        }
        if ($outAlwaysKeep !== []) {
            $outSample['always_keep'] = $outAlwaysKeep;
        }

        $typeGate = $sample['type_gate'] ?? null;
        if (is_array($typeGate)) {
            /** @var array<string, bool> $outTypeGate */
            $outTypeGate = [];
            foreach ($typeGate as $type => $on) {
                $outTypeGate[Cast::str($type)] = Cast::bool($on);
            }
            $outSample['type_gate'] = $outTypeGate;
        }

        if ($outSample !== []) {
            $out['sample'] = $outSample;
        }

        if (array_key_exists('scrub', $input) && is_array($input['scrub'])) {
            $out['scrub'] = array_values(array_filter(
                array_map(fn ($s) => Cast::str($s), $input['scrub']),
                fn (string $s) => $s !== '',
            ));
        }

        if (array_key_exists('host_interval', $input)) {
            $out['host_interval'] = max(1, Cast::int($input['host_interval']));
        }

        // Query capture threshold (ms). 0 means "capture every query" (full); a
        // positive value drops queries faster than it on the child. Stored under
        // query.capture_min_ms so it maps straight onto the KnobMap path.
        $query = Cast::arr($input['query'] ?? null);
        if (array_key_exists('capture_min_ms', $query)) {
            $out['query'] = ['capture_min_ms' => max(0, Cast::int($query['capture_min_ms']))];
        }

        if (is_array($input['capture'] ?? null)) {
            /** @var array<string, bool> $outCapture */
            $outCapture = [];

            // Only the two privacy toggles are admin-controllable. We deliberately
            // accept ONLY pii/mail_body and never `disable_credential_scrub`:
            // lowering the credential-scrub floor is a dangerous, local decision
            // that must be made on the child via its .env — never pushed from the
            // dashboard. Any disable_credential_scrub key in the input is ignored.
            foreach (['pii', 'mail_body'] as $k) {
                if (array_key_exists($k, $input['capture'])) {
                    $outCapture[$k] = Cast::bool($input['capture'][$k]);
                }
            }

            if ($outCapture !== []) {
                $out['capture'] = $outCapture;
            }
        }

        return $out;
    }
}
