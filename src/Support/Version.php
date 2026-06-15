<?php

namespace VictorStochero\Warden\Support;

use Composer\InstalledVersions;
use OutOfBoundsException;

/**
 * The installed Warden package version, read from Composer's generated metadata.
 * `InstalledVersions` ships with the Composer autoloader (composer-runtime-api),
 * so this stays within the zero-runtime-dependency guarantee. Best-effort: never
 * throws into the host — an unresolvable version returns null.
 */
class Version
{
    public static function current(): ?string
    {
        if (! class_exists(InstalledVersions::class)) {
            return null;
        }

        try {
            return InstalledVersions::getPrettyVersion('victorstochero/warden');
        } catch (OutOfBoundsException) {
            return null;
        }
    }
}
