<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use VictorStochero\Warden\Tests\TestCase;

/**
 * #8 — the ingest rate limiter must be keyed by something the client cannot
 * freely randomize (the IP), not by an attacker-controlled header, so the limit
 * can't be trivially evaded.
 */
class IngestRateLimiterKeyTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function limitFor(Request $request): Limit
    {
        $callback = RateLimiter::limiter('warden-ingest');
        $this->assertNotNull($callback);

        $limit = $callback($request);

        return is_array($limit) ? $limit[0] : $limit;
    }

    private function request(string $ip, string $token): Request
    {
        $request = Request::create('/warden/ingest', 'POST');
        $request->server->set('REMOTE_ADDR', $ip);
        $request->headers->set('X-Warden-Token', $token);

        return $request;
    }

    public function test_two_tokens_from_the_same_ip_share_the_bucket(): void
    {
        $a = $this->limitFor($this->request('10.0.0.1', 'token-a'))->key;
        $b = $this->limitFor($this->request('10.0.0.1', 'token-b'))->key;

        $this->assertSame($a, $b, 'rotating the token must not change the limiter key');
    }

    public function test_different_ips_get_different_buckets(): void
    {
        $a = $this->limitFor($this->request('10.0.0.1', 'token-a'))->key;
        $b = $this->limitFor($this->request('10.0.0.2', 'token-a'))->key;

        $this->assertNotSame($a, $b);
    }
}
