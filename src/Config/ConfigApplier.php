<?php

namespace VictorStochero\Warden\Config;

use Illuminate\Contracts\Config\Repository;

/** Applies a sparse remote config to config('warden.child.*') honouring .env precedence. */
final class ConfigApplier
{
    /** @param array<string, mixed> $remote */
    public static function apply(Repository $config, array $remote): void
    {
        foreach (KnobMap::all() as $knob => $envVar) {
            $value = data_get($remote, $knob);

            if ($value === null) {
                continue;
            }

            if ($envVar !== null && getenv($envVar) !== false) {
                continue; // .env explicito vence
            }

            $config->set('warden.child.'.$knob, $value);
        }
    }
}
