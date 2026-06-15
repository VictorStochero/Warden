<?php

namespace VictorStochero\Warden\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VictorStochero\Warden\Analysis\QueryHealthAnalyzer;

class QueryHealthAnalyzerTest extends TestCase
{
    /** @param array<string,mixed> $payload */
    private function q(string $trace, array $payload, int $us = 1000): array
    {
        return ['trace_id' => $trace, 'duration_us' => $us, 'payload' => $payload];
    }

    public function test_flags_n_plus_one_within_a_trace(): void
    {
        $events = [
            $this->q('t1', ['sql' => 'select * from posts where id = 1']),
            $this->q('t1', ['sql' => 'select * from posts where id = 2']),
            $this->q('t1', ['sql' => 'select * from posts where id = 3']),
            $this->q('t1', ['sql' => 'select * from users where id = 9']),
        ];

        $r = (new QueryHealthAnalyzer)->analyze($events);

        $this->assertCount(1, $r['n_plus_one']);
        $this->assertSame(3, $r['n_plus_one'][0]['count']);
        $this->assertSame('t1', $r['n_plus_one'][0]['trace_id']);
    }

    public function test_flags_exact_duplicates_same_bindings(): void
    {
        $events = [
            $this->q('t1', ['sql' => 'select * from users where id = ?', 'bindings' => [42]]),
            $this->q('t1', ['sql' => 'select * from users where id = ?', 'bindings' => [42]]),
        ];

        $r = (new QueryHealthAnalyzer)->analyze($events);

        $this->assertCount(1, $r['duplicates']);
        $this->assertSame(2, $r['duplicates'][0]['count']);
    }

    public function test_does_not_flag_duplicate_when_bindings_differ(): void
    {
        $events = [
            $this->q('t1', ['sql' => 'select * from users where id = ?', 'bindings' => [1]]),
            $this->q('t1', ['sql' => 'select * from users where id = ?', 'bindings' => [2]]),
        ];

        $r = (new QueryHealthAnalyzer)->analyze($events);

        $this->assertCount(0, $r['duplicates']);
    }

    public function test_flags_select_star(): void
    {
        $r = (new QueryHealthAnalyzer)->analyze([
            $this->q('t1', ['sql' => 'select * from orders']),
        ]);

        $this->assertCount(1, $r['select_star']);
    }

    public function test_flags_write_without_where(): void
    {
        $r = (new QueryHealthAnalyzer)->analyze([
            $this->q('t1', ['sql' => 'update users set active = 1']),
            $this->q('t2', ['sql' => 'delete from sessions']),
            $this->q('t3', ['sql' => 'update users set active = 1 where id = 5']),
        ]);

        $this->assertCount(2, $r['no_where']);
    }

    public function test_flags_fat_request(): void
    {
        $events = [];
        for ($i = 0; $i < 60; $i++) {
            $events[] = $this->q('fat', ['sql' => 'select '.$i.' from t where id = '.$i]);
        }

        $r = (new QueryHealthAnalyzer(fatRequestThreshold: 50))->analyze($events);

        $this->assertCount(1, $r['fat_requests']);
        $this->assertSame('fat', $r['fat_requests'][0]['trace_id']);
        $this->assertGreaterThan(50, $r['fat_requests'][0]['count']);
    }

    public function test_flags_slow_query(): void
    {
        $r = (new QueryHealthAnalyzer(slowQueryUs: 100_000))->analyze([
            $this->q('t1', ['sql' => 'select * from big'], 250_000),
            $this->q('t1', ['sql' => 'select * from small'], 5_000),
        ]);

        $this->assertCount(1, $r['slow']);
        $this->assertSame(250_000, $r['slow'][0]['duration_us']);
    }
}
