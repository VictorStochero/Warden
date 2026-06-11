<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * The child reports its own app.timezone in the ingest body; the parent
 * auto-sets wdn_projects.timezone from it when the value is a valid IANA
 * identifier that differs from what is stored. The manual selector is gone —
 * timezone is now derived, never set by hand.
 */
class TimezoneAutoDetectTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function ship(string $timezone, ?Project $project = null): TestResponse
    {
        $project ??= Project::create([
            'name' => 'Demo', 'slug' => 'demo',
            'token' => 'ptoken', 'secret' => 'psecret', 'active' => true,
        ]);

        $body = (string) json_encode([
            'schema_version' => 2, 'project' => 'demo', 'sent_at' => Carbon::now()->getTimestamp(),
            'app_timezone' => $timezone, 'batches' => [],
        ], JSON_UNESCAPED_SLASHES);

        $server = $this->transformHeadersToServerVars([
            'X-Warden-Token' => 'ptoken',
            'X-Warden-Signature' => hash_hmac('sha256', $body, 'psecret'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        return $this->call('POST', route('warden.ingest'), [], [], [], $server, $body);
    }

    public function test_ingest_auto_sets_project_timezone_from_child_report(): void
    {
        $this->ship('America/Mexico_City')->assertStatus(202);

        $this->assertSame(
            'America/Mexico_City',
            Project::query()->where('slug', 'demo')->value('timezone'),
        );
    }

    public function test_ingest_ignores_invalid_timezone(): void
    {
        $project = Project::create([
            'name' => 'Demo', 'slug' => 'demo',
            'token' => 'ptoken', 'secret' => 'psecret', 'active' => true,
            'timezone' => 'America/Sao_Paulo',
        ]);

        $this->ship('Not/AZone', $project)->assertStatus(202);

        $this->assertSame(
            'America/Sao_Paulo',
            Project::query()->where('slug', 'demo')->value('timezone'),
        );
    }
}
