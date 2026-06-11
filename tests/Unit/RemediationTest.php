<?php

namespace VictorStochero\Warden\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VictorStochero\Warden\Console\Audit\Remediation;

class RemediationTest extends TestCase
{
    public function test_open_upper_bound_recommends_that_version(): void
    {
        $this->assertSame(
            ['type' => 'upgrade', 'version' => '1.10.1'],
            Remediation::fromComposerConstraint('<1.10.1'),
        );
    }

    public function test_and_range_recommends_the_upper_bound(): void
    {
        $this->assertSame(
            ['type' => 'upgrade', 'version' => '2.0.0'],
            Remediation::fromComposerConstraint('>=1.0.0,<2.0.0'),
        );
    }

    public function test_inclusive_upper_bound_recommends_above(): void
    {
        $this->assertSame(
            ['type' => 'upgrade_above', 'version' => '1.8.3'],
            Remediation::fromComposerConstraint('>=1.1.0,<=1.8.3'),
        );
    }

    public function test_or_clauses_take_the_highest_upper_bound(): void
    {
        $this->assertSame(
            ['type' => 'upgrade', 'version' => '2.3.4'],
            Remediation::fromComposerConstraint('>=1.0,<1.5|>=2.0,<2.3.4'),
        );
    }

    public function test_open_ended_range_has_no_known_fix(): void
    {
        $this->assertSame(
            ['type' => 'none', 'version' => null],
            Remediation::fromComposerConstraint('>=1.0.0'),
        );
    }

    public function test_empty_constraint_is_unknown(): void
    {
        $this->assertSame(
            ['type' => 'unknown', 'version' => null],
            Remediation::fromComposerConstraint(''),
        );
    }

    public function test_npm_fix_object_recommends_the_version(): void
    {
        $this->assertSame(
            ['type' => 'upgrade', 'version' => '4.17.21'],
            Remediation::fromNpm(['name' => 'lodash', 'version' => '4.17.21']),
        );
    }

    public function test_npm_fix_true_is_a_generic_fix_available(): void
    {
        $this->assertSame(
            ['type' => 'fix_available', 'version' => null],
            Remediation::fromNpm(true),
        );
    }

    public function test_npm_fix_false_has_no_known_fix(): void
    {
        $this->assertSame(
            ['type' => 'none', 'version' => null],
            Remediation::fromNpm(false),
        );
    }

    public function test_npm_fix_missing_is_unknown(): void
    {
        $this->assertSame(
            ['type' => 'unknown', 'version' => null],
            Remediation::fromNpm(null),
        );
    }
}
