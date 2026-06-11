<?php

namespace VictorStochero\Warden\Config;

use Illuminate\Contracts\Config\Repository;

/**
 * Child-side application of the parent-pushed sparse config, with .env taking
 * precedence over the parent. Reads the local cache and delegates to
 * ConfigApplier. Never throws — config must never break the host boot (RNF-2).
 */
final class RemoteConfig
{
    public function apply(Repository $config): void
    {
        try {
            $remote = ConfigCache::read();

            if ($remote === []) {
                return;
            }

            ConfigApplier::apply($config, $remote);
        } catch (\Throwable) {
            // Config remoto nunca pode quebrar o boot do host.
        }
    }
}
