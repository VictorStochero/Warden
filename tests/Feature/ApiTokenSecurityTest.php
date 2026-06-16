<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Carbon;
use VictorStochero\Warden\Models\ApiToken;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Security hardening of the read-only API tokens (§9.5): an indexable prefix +
 * timing-safe hash comparison, mass-assignment hygiene, and a throttled
 * last_used_at write on the hot read path.
 */
class ApiTokenSecurityTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function setUp(): void
    {
        parent::setUp();
        Project::firstOrCreate(['slug' => 'demo'], ['name' => 'Demo', 'token' => 't', 'secret' => 's', 'active' => true]);
    }

    public function test_mint_persists_an_indexable_prefix(): void
    {
        [$token, $plain] = ApiToken::mint('ci');

        $this->assertNotNull($token->prefix);
        $this->assertNotSame('', $token->prefix);
        // The prefix is a stable slice of the plaintext, so a lookup can index on it.
        $this->assertStringStartsWith($token->prefix, $plain);
    }

    public function test_find_by_plaintext_resolves_a_valid_token(): void
    {
        [$token, $plain] = ApiToken::mint('ci');

        $found = ApiToken::findByPlaintext($plain);

        $this->assertNotNull($found);
        $this->assertSame($token->id, $found->id);
    }

    public function test_a_known_prefix_with_a_wrong_hash_does_not_resolve(): void
    {
        [$token, $plain] = ApiToken::mint('ci');

        // Same indexable prefix, different secret body → must fail the hash_equals.
        $forged = $token->prefix.str_repeat('z', 40);

        $this->assertNull(ApiToken::findByPlaintext($forged));
    }

    public function test_last_used_at_is_not_rewritten_within_the_throttle_window(): void
    {
        [$token, $plain] = ApiToken::mint('ci');

        $this->withToken($plain)->getJson(route('warden.api.overview'))->assertOk();
        $first = $token->fresh()->last_used_at;
        $this->assertNotNull($first);

        // A second call moments later must not issue another UPDATE.
        Carbon::setTestNow(Carbon::now()->addSeconds(5));
        $this->withToken($plain)->getJson(route('warden.api.overview'))->assertOk();
        $second = $token->fresh()->last_used_at;

        $this->assertTrue($first->equalTo($second), 'last_used_at was rewritten inside the throttle window');

        Carbon::setTestNow();
    }

    public function test_last_used_at_is_refreshed_after_the_throttle_window(): void
    {
        [$token, $plain] = ApiToken::mint('ci');

        $this->withToken($plain)->getJson(route('warden.api.overview'))->assertOk();
        $first = $token->fresh()->last_used_at;

        Carbon::setTestNow(Carbon::now()->addSeconds(120));
        $this->withToken($plain)->getJson(route('warden.api.overview'))->assertOk();
        $second = $token->fresh()->last_used_at;

        $this->assertTrue($second->greaterThan($first), 'last_used_at was not refreshed past the throttle window');

        Carbon::setTestNow();
    }
}
