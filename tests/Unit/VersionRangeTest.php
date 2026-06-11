<?php

namespace VictorStochero\Warden\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VictorStochero\Warden\Support\VersionRange;

class VersionRangeTest extends TestCase
{
    public function test_open_upper_bound(): void
    {
        $this->assertTrue(VersionRange::matches('1.5.0', '<1.10.1'));
        $this->assertFalse(VersionRange::matches('1.10.1', '<1.10.1'));
        $this->assertFalse(VersionRange::matches('2.0.0', '<1.10.1'));
    }

    public function test_and_range(): void
    {
        $this->assertTrue(VersionRange::matches('1.5.0', '>=1.0.0,<2.0.0'));
        $this->assertFalse(VersionRange::matches('2.0.0', '>=1.0.0,<2.0.0'));
        $this->assertFalse(VersionRange::matches('0.9.0', '>=1.0.0,<2.0.0'));
    }

    public function test_or_of_two_ranges(): void
    {
        $c = '>=1.0.0,<1.5.0|>=2.0.0,<2.3.4';
        $this->assertTrue(VersionRange::matches('1.2.0', $c), 'first clause');
        $this->assertTrue(VersionRange::matches('2.1.0', $c), 'second clause');
        $this->assertFalse(VersionRange::matches('1.8.0', $c), 'between the clauses');
        $this->assertFalse(VersionRange::matches('2.3.4', $c), 'upper bound exclusive');
    }

    public function test_double_pipe_or_is_also_accepted(): void
    {
        $this->assertTrue(VersionRange::matches('1.0.0', '<1.0.1 || >=2.0.0'));
    }

    public function test_v_prefix_is_normalized(): void
    {
        $this->assertTrue(VersionRange::matches('v1.5.0', '<1.10.1'));
        $this->assertTrue(VersionRange::matches('1.5.0', '<v1.10.1'));
    }

    public function test_numeric_segments_compare_as_numbers_not_lexically(): void
    {
        // 1.10 is greater than 1.9 — a lexical compare would get this wrong.
        $this->assertTrue(VersionRange::matches('1.9.0', '<1.10.0'));
        $this->assertFalse(VersionRange::matches('1.10.0', '<1.9.0'));
    }

    public function test_exact_pin(): void
    {
        $this->assertTrue(VersionRange::matches('1.2.3', '=1.2.3'));
        $this->assertTrue(VersionRange::matches('1.2.3', '1.2.3'));
        $this->assertFalse(VersionRange::matches('1.2.4', '=1.2.3'));
    }

    public function test_unparseable_or_empty_constraint_is_conservatively_affected(): void
    {
        // Security posture: if we cannot evaluate the range, never hide a
        // possible advisory — surface it rather than risk a false "safe".
        $this->assertTrue(VersionRange::matches('1.0.0', ''));
        $this->assertTrue(VersionRange::matches('1.0.0', 'not-a-constraint'));
    }
}
