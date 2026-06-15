<?php

namespace VictorStochero\Warden\Tests\Unit;

use VictorStochero\Warden\Support\Version;
use VictorStochero\Warden\Tests\TestCase;

class VersionTest extends TestCase
{
    public function test_current_returns_the_installed_package_version(): void
    {
        // In the package's own suite the package is the root Composer package,
        // so its pretty version is always resolvable (e.g. dev-main).
        $version = Version::current();

        $this->assertNotNull($version);
        $this->assertNotSame('', $version);
    }
}
