<?php

namespace VictorStochero\Warden\Config;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\DB;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Json;

/**
 * Parent self-monitor: applies the parent project's own sparse config to
 * config('warden.child.*') at boot, reusing the same .env-precedence rules as
 * the remote child path (ConfigApplier). Reads through the dedicated wdn
 * connection and never throws — a missing table/row means "no overrides".
 */
final class SelfMonitorConfig
{
    public function apply(Repository $config): void
    {
        try {
            $conn = Cast::str($config->get('warden.connection')) ?: null;
            $slug = Cast::str($config->get('warden.parent.self_project'), 'parent');

            $row = DB::connection($conn)->table('wdn_projects')->where('slug', $slug)->first(['config']);

            if ($row === null || ! is_string($row->config ?? null)) {
                return;
            }

            $remote = Json::decode($row->config);

            if ($remote === []) {
                return;
            }

            ConfigApplier::apply($config, $remote);
        } catch (\Throwable) {
            // Self-config nunca quebra o boot do parent (RNF-2).
        }
    }
}
