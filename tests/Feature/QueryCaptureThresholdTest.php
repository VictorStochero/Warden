<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use VictorStochero\Warden\Config\ConfigCache;
use VictorStochero\Warden\Config\RemoteConfig;
use VictorStochero\Warden\Recording\Recorders\QueryRecorder;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

class QueryCaptureThresholdTest extends TestCase
{
    protected function tearDown(): void
    {
        ConfigCache::forget();
        putenv('WARDEN_QUERY_MIN_MS');
        parent::tearDown();
    }

    /** @return list<string> the SQL of every query event in the buffer */
    private function capturedSql(Warden $observer): array
    {
        $sql = [];
        foreach ($observer->buffer()->all() as $event) {
            if (($event['type'] ?? null) === 'query') {
                $sql[] = (string) ($event['payload']['sql'] ?? '');
            }
        }

        return $sql;
    }

    public function test_threshold_drops_fast_queries_and_keeps_slow_ones(): void
    {
        config()->set('warden.connection', null);
        config()->set('warden.child.query.capture_min_ms', 100);

        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');
        $observer->keep();
        $this->app->make(QueryRecorder::class)->register();

        $conn = DB::connection();
        event(new QueryExecuted('select /*fast*/ 1', [], 5.0, $conn));
        event(new QueryExecuted('select /*slow*/ 2', [], 250.0, $conn));

        $sql = $this->capturedSql($observer);

        $this->assertNotContains('select /*fast*/ 1', $sql, 'fast query must be dropped');
        $this->assertContains('select /*slow*/ 2', $sql, 'slow query must be kept');
    }

    public function test_zero_threshold_keeps_every_query(): void
    {
        config()->set('warden.connection', null);
        config()->set('warden.child.query.capture_min_ms', 0);

        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');
        $observer->keep();
        $this->app->make(QueryRecorder::class)->register();

        $conn = DB::connection();
        event(new QueryExecuted('select /*tiny*/ 1', [], 0.1, $conn));

        $this->assertContains('select /*tiny*/ 1', $this->capturedSql($observer));
    }

    public function test_remote_config_pushes_query_threshold_when_env_absent(): void
    {
        ConfigCache::write(1, ['query' => ['capture_min_ms' => 100]]);
        config()->set('warden.child.query.capture_min_ms', null);

        (new RemoteConfig)->apply($this->app['config']);

        $this->assertSame(100, config('warden.child.query.capture_min_ms'));
    }

    public function test_explicit_env_wins_over_pushed_query_threshold(): void
    {
        putenv('WARDEN_QUERY_MIN_MS=0');
        ConfigCache::write(1, ['query' => ['capture_min_ms' => 100]]);
        config()->set('warden.child.query.capture_min_ms', 0);

        (new RemoteConfig)->apply($this->app['config']);

        $this->assertSame(0, config('warden.child.query.capture_min_ms'));
    }
}
