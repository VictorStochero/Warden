<?php

namespace VictorStochero\Warden\Tests\Unit;

use VictorStochero\Warden\Support\PackageVersion;
use VictorStochero\Warden\Tests\TestCase;

class PackageVersionTest extends TestCase
{
    public function test_is_stable_accepts_plain_dotted_digits_only(): void
    {
        $this->assertTrue(PackageVersion::isStable('0.4.0'));
        $this->assertTrue(PackageVersion::isStable('v1.2.3'));
        $this->assertTrue(PackageVersion::isStable('10.0'));

        $this->assertFalse(PackageVersion::isStable('0.4.0-beta.1'));
        $this->assertFalse(PackageVersion::isStable('1.0.0-RC1'));
        $this->assertFalse(PackageVersion::isStable('dev-main'));
    }

    public function test_latest_picks_highest_stable_and_ignores_prereleases_by_default(): void
    {
        $versions = ['v0.3.2', 'v0.4.0-beta.1', 'v0.4.0', 'v0.3.10', 'dev-main'];

        $this->assertSame('0.4.0', PackageVersion::latest($versions));
    }

    public function test_latest_can_include_prereleases(): void
    {
        $versions = ['v0.4.0', 'v0.5.0-RC1'];

        $this->assertSame('0.5.0-RC1', PackageVersion::latest($versions, includePrereleases: true));
    }

    public function test_latest_returns_null_when_nothing_qualifies(): void
    {
        $this->assertNull(PackageVersion::latest(['dev-main', '1.0.0-beta']));
        $this->assertNull(PackageVersion::latest([]));
    }

    public function test_is_newer_compares_semver_ignoring_v_prefix(): void
    {
        $this->assertTrue(PackageVersion::isNewer('v0.4.0', '0.3.2'));
        $this->assertFalse(PackageVersion::isNewer('0.3.2', '0.3.2'));
        $this->assertFalse(PackageVersion::isNewer('0.3.1', 'v0.3.2'));
    }
}
