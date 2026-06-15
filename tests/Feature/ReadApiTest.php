<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Models\ApiToken;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Read-only API (§5.7): token-authed JSON access to the read layer for
 * automation and external dashboards.
 */
class ReadApiTest extends TestCase
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

    public function test_a_valid_token_can_read_the_overview(): void
    {
        [, $plain] = ApiToken::mint('ci');

        $this->withToken($plain)
            ->getJson(route('warden.api.overview'))
            ->assertOk()
            ->assertJsonStructure(['open_issues', 'open_incidents', 'throughput', 'projects']);
    }

    public function test_a_valid_token_can_read_project_kpis(): void
    {
        [, $plain] = ApiToken::mint('ci');

        $this->withToken($plain)
            ->getJson(route('warden.api.project', 'demo'))
            ->assertOk()
            ->assertJsonStructure(['project' => ['slug'], 'range', 'kpis' => ['throughput', 'error_rate']]);
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $this->withToken('wdn_not-a-real-token')
            ->getJson(route('warden.api.overview'))
            ->assertStatus(401);
    }

    public function test_a_missing_token_is_rejected(): void
    {
        $this->getJson(route('warden.api.overview'))->assertStatus(401);
    }

    public function test_using_a_token_stamps_last_used(): void
    {
        [$token, $plain] = ApiToken::mint('ci');

        $this->assertNull($token->last_used_at);

        $this->withToken($plain)->getJson(route('warden.api.overview'))->assertOk();

        $this->assertNotNull($token->fresh()->last_used_at);
    }
}
