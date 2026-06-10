<?php

namespace VictorStochero\Warden\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Alerting\Evaluator;
use VictorStochero\Warden\Contracts\Aggregator;
use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Issues\IssueProcessor;
use VictorStochero\Warden\Models\Heartbeat;
use VictorStochero\Warden\Models\Issue;
use VictorStochero\Warden\Models\Project;

/**
 * Not a real test — a renderer. Run with WARDEN_SHOTS=1 to seed a realistic
 * dataset and dump each dashboard page to build/shots/*.html for screenshots.
 *
 *   WARDEN_SHOTS=1 vendor/bin/phpunit --filter render_dashboard
 */
class ScreenshotTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    public function test_render_dashboard(): void
    {
        if (getenv('WARDEN_SHOTS') !== '1') {
            $this->markTestSkipped('Set WARDEN_SHOTS=1 to render dashboard HTML.');
        }

        Gate::define('viewWarden', fn ($u = null) => true);
        $this->app['config']->set('warden.dashboard.refresh', 0); // no meta-refresh during capture

        $project = $this->seedFleet();
        $issueId = Issue::where('project_id', $project->id)->orderByDesc('count')->value('id');

        $dir = __DIR__.'/../build/shots';
        @mkdir($dir, 0777, true);

        $pages = [
            'overview' => route('warden.overview'),
            'project' => route('warden.project', ['project' => 'storefront', 'range' => '6h']),
            'project-queries' => route('warden.project.section', ['project' => 'storefront', 'section' => 'queries', 'range' => '6h']),
            'project-jobs' => route('warden.project.section', ['project' => 'storefront', 'section' => 'jobs', 'range' => '6h']),
            'issues' => route('warden.issues', 'storefront'),
            'issue' => route('warden.issue', ['storefront', $issueId]),
            'traces' => route('warden.traces', 'storefront'),
            'trace' => route('warden.trace', ['storefront', 'trace-checkout']),
        ];

        foreach ($pages as $name => $url) {
            $html = $this->get($url)->getContent();
            file_put_contents("{$dir}/{$name}.html", $html);
        }

        $this->assertFileExists("{$dir}/overview.html");
    }

    protected function seedFleet(): Project
    {
        // Three projects for the overview grid; "storefront" gets the rich data.
        $storefront = Project::create(['name' => 'Storefront', 'slug' => 'storefront', 'token' => 't1', 'secret' => 's1', 'active' => true]);
        Project::create(['name' => 'Billing API', 'slug' => 'billing-api', 'token' => 't2', 'secret' => 's2', 'active' => true]);
        Project::create(['name' => 'Admin Panel', 'slug' => 'admin-panel', 'token' => 't3', 'secret' => 's3', 'active' => true]);

        $ingestor = $this->app->make(Ingestor::class);
        $events = [];

        $routes = [
            ['GET', '/checkout', 90, 520],
            ['POST', '/cart/add', 200, 60],
            ['GET', '/products/{id}', 320, 140],
            ['GET', '/api/orders', 70, 240],
            ['GET', '/dashboard', 40, 880],
        ];

        // 36 buckets of request/query/cache/host history (~3h at 5-min steps).
        for ($i = 36; $i >= 0; $i--) {
            $at = Carbon::now()->subMinutes($i * 5);
            $ts = $at->format('Y-m-d H:i:s.u');

            foreach ($routes as [$method, $route, $base, $jitter]) {
                $hits = random_int(2, 9);
                for ($h = 0; $h < $hits; $h++) {
                    $duration = ($base + random_int(0, $jitter)) * 1000;
                    $status = (random_int(1, 40) === 1) ? 500 : 200;
                    $events[] = $this->event('request', 'tr'.$i.$route.$h, $ts, $duration, [
                        'method' => $method, 'route' => $route, 'path' => $route, 'status' => $status,
                    ]);
                    $events[] = $this->event('query', 'tr'.$i.$route.$h, $ts, random_int(200, 4000), [
                        'sql' => 'select * from orders where customer_id = ? limit 1',
                    ]);
                    $events[] = $this->event('cache', 'tr'.$i.$route.$h, $ts, null, [
                        'action' => random_int(0, 3) ? 'hit' : 'miss', 'store' => 'redis',
                        'key' => 'orders', 'hit' => (bool) random_int(0, 3),
                    ]);
                }
            }

            // A slow N+1-prone query on the products page.
            $events[] = $this->event('query', 'q'.$i, $ts, random_int(4000, 22000), [
                'sql' => 'select * from product_variants where product_id = ?',
            ]);

            $events[] = $this->event('host', 'h'.$i, $ts, null, [
                'hostname' => 'web-1',
                'cpu' => random_int(15, 78) + 0.0,
                'memory' => ['used_percent' => random_int(40, 72)],
                'load' => [1 => random_int(1, 9) / 10 + 0.5],
                'disk' => ['used_percent' => 61],
            ]);
        }

        // Jobs (some failures), across a few buckets.
        foreach (['App\\Jobs\\ProcessPayment' => 0, 'App\\Jobs\\SendReceipt' => 0, 'App\\Jobs\\SyncInventory' => 3] as $class => $fails) {
            for ($n = 0; $n < 14; $n++) {
                $at = Carbon::now()->subMinutes(random_int(0, 120))->format('Y-m-d H:i:s.u');
                $status = $n < $fails ? 'failed' : 'processed';
                $events[] = $this->event('job', 'job'.$class.$n, $at, random_int(8000, 240000), [
                    'status' => $status, 'class' => $class, 'queue' => 'default',
                ]);
            }
        }

        // Outgoing HTTP + scheduled tasks + exceptions.
        foreach (['api.stripe.com' => 0, 'api.shippo.com' => 2] as $host => $errs) {
            for ($n = 0; $n < 10; $n++) {
                $at = Carbon::now()->subMinutes(random_int(0, 90))->format('Y-m-d H:i:s.u');
                $events[] = $this->event('http', 'h'.$host.$n, $at, random_int(40000, 900000), [
                    'method' => 'POST', 'host' => $host, 'status' => $n < $errs ? 500 : 200,
                ]);
            }
        }

        $hb = 'schedule:'.md5('orders:reconcile|*/5 * * * *');
        for ($n = 0; $n < 6; $n++) {
            $at = Carbon::now()->subMinutes($n * 5)->format('Y-m-d H:i:s.u');
            $events[] = $this->event('schedule', 'sch'.$n, $at, random_int(120000, 900000), [
                'task' => 'orders:reconcile', 'expression' => '*/5 * * * *', 'status' => 'finished', 'heartbeat' => $hb,
            ]);
        }

        $exceptions = [
            ['App\\Exceptions\\PaymentDeclined', 'Card declined for order 8841', 23],
            ['Illuminate\\Database\\QueryException', 'SQLSTATE[40001]: deadlock detected on orders', 7],
            ['TypeError', 'Argument #1 must be of type Money, null given', 3],
        ];
        foreach ($exceptions as [$class, $msg, $count]) {
            for ($n = 0; $n < $count; $n++) {
                $at = Carbon::now()->subMinutes(random_int(0, 160))->format('Y-m-d H:i:s.u');
                $events[] = $this->event('exception', 'exc'.$class.$n, $at, null, [
                    'class' => $class, 'message' => $msg, 'user_id' => random_int(1, 40),
                    'stack' => [
                        ['class' => $class, 'function' => 'handle', 'file' => '/app/Domain/Checkout.php', 'line' => 142],
                        ['class' => 'App\\Http\\Controllers\\CheckoutController', 'function' => 'store', 'file' => '/app/Http/Controllers/CheckoutController.php', 'line' => 58],
                    ],
                ]);
            }
        }

        // A detailed trace for the waterfall view.
        $traceAt = Carbon::now()->subMinutes(4);
        $base = (float) $traceAt->format('U.u');
        $events[] = $this->eventAt('request', 'trace-checkout', 's-root', null, $base, absUs: 486000, payload: ['method' => 'POST', 'route' => '/checkout', 'path' => '/checkout', 'status' => 200]);
        $offset = 0.004;
        foreach ([['query', 'select * from carts where id = ?', 12000], ['cache', null, 1500], ['query', 'select * from cart_items where cart_id = ?', 9000]] as $k => $row) {
            $events[] = $this->eventAt($row[0], 'trace-checkout', 's'.$k, 's-root', $base + $offset, absUs: $row[2], payload: $row[0] === 'query' ? ['sql' => $row[1]] : ['action' => 'hit', 'store' => 'redis', 'key' => 'pricing', 'hit' => true]);
            $offset += $row[2] / 1_000_000 + 0.002;
        }
        // N+1: same query repeated.
        for ($k = 0; $k < 5; $k++) {
            $events[] = $this->eventAt('query', 'trace-checkout', 'sn'.$k, 's-root', $base + $offset, absUs: 7000, payload: ['sql' => 'select * from product_variants where product_id = '.$k]);
            $offset += 0.009;
        }
        $events[] = $this->eventAt('http', 'trace-checkout', 's-http', 's-root', $base + $offset, absUs: 180000, payload: ['method' => 'POST', 'host' => 'api.stripe.com', 'status' => 200]);
        $offset += 0.182;
        $events[] = $this->eventAt('job', 'trace-checkout', 's-job', 's-root', $base + $offset, absUs: 4000, payload: ['status' => 'queued', 'class' => 'App\\Jobs\\SendReceipt', 'queue' => 'mail']);

        $ingestor->ingest('storefront', $events);

        $aggregator = $this->app->make(Aggregator::class);
        foreach (['request', 'query', 'cache', 'job', 'http', 'schedule', 'exception', 'host'] as $type) {
            $aggregator->rollup($storefront->id, $type);
        }
        $this->app->make(IssueProcessor::class)->process($storefront->id);

        // A missed heartbeat -> open incident on the overview/project.
        Heartbeat::create([
            'project_id' => $storefront->id, 'key' => 'schedule:queue-worker',
            'expected_interval' => 60, 'grace' => 30,
            'last_seen_at' => Carbon::now()->subMinutes(15), 'alerted' => false,
        ]);
        $this->app->make(Evaluator::class)->evaluate($storefront->id);

        return $storefront;
    }

    /** @return array<string, mixed> */
    protected function event(string $type, string $trace, string $at, ?int $durationUs, array $payload): array
    {
        return [
            'type' => $type, 'trace_id' => $trace, 'occurred_at' => $at,
            'duration_us' => $durationUs, 'payload' => $payload,
        ];
    }

    /** @return array<string, mixed> */
    protected function eventAt(string $type, string $trace, string $span, ?string $parent, float $epoch, int $absUs, array $payload): array
    {
        return [
            'type' => $type, 'trace_id' => $trace, 'span_id' => $span, 'parent_span_id' => $parent,
            'occurred_at' => Carbon::createFromTimestamp($epoch)->format('Y-m-d H:i:s.u'),
            'duration_us' => $absUs, 'payload' => $payload,
        ];
    }
}
