<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Covers the control-plane version handshake: the child ships its known
 * config_version; the parent's ingest response always echoes config_version
 * and includes the config document only when the child is stale.
 */
class ConfigDistributionTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function seedProject(array $config, int $version): Project
    {
        return Project::create([
            'name' => 'Demo', 'slug' => 'demo',
            'token' => 'ptoken', 'secret' => 'psecret', 'active' => true,
            'config' => $config, 'config_version' => $version,
        ]);
    }

    private function ship(int $childVersion): TestResponse
    {
        $body = (string) json_encode([
            'schema_version' => 2, 'project' => 'demo', 'sent_at' => Carbon::now()->getTimestamp(),
            'config_version' => $childVersion, 'batches' => [],
        ], JSON_UNESCAPED_SLASHES);

        $server = $this->transformHeadersToServerVars([
            'X-Warden-Token' => 'ptoken',
            'X-Warden-Signature' => hash_hmac('sha256', $body, 'psecret'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        return $this->call('POST', route('warden.ingest'), [], [], [], $server, $body);
    }

    public function test_response_includes_config_when_child_version_is_stale(): void
    {
        $this->seedProject(['host_interval' => 30], 5);

        $res = $this->ship(childVersion: 0);

        $res->assertStatus(202);
        $res->assertJsonPath('config_version', 5);
        $res->assertJsonPath('config.host_interval', 30);
    }

    public function test_response_omits_config_when_versions_match(): void
    {
        $this->seedProject(['host_interval' => 30], 5);

        $res = $this->ship(childVersion: 5);

        $res->assertStatus(202);
        $res->assertJsonPath('config_version', 5);
        $res->assertJsonMissingPath('config');
    }
}
