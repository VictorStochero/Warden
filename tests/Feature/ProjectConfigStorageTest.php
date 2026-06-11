<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Config\KnobMap;
use VictorStochero\Warden\Config\ProjectConfig;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class ProjectConfigStorageTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    public function test_knob_map_paths_resolve_against_default_config(): void
    {
        foreach (KnobMap::keys() as $knob) {
            $this->assertTrue(
                config()->has('warden.child.'.$knob),
                "Knob {$knob} nao existe em config('warden.child')"
            );
        }
    }

    public function test_project_config_validates_and_keeps_only_known_sparse_knobs(): void
    {
        $clean = ProjectConfig::sanitize([
            'host_interval' => '45',
            'sample' => ['traces' => ['request' => 0.5]],
            'unknown_knob' => 'nope',
            'recorders' => ['request', 'query'],
        ]);

        $this->assertSame(45, $clean['host_interval']);
        $this->assertSame(0.5, $clean['sample']['traces']['request']);
        $this->assertArrayNotHasKey('unknown_knob', $clean);
        $this->assertSame(['request', 'query'], $clean['recorders']);
    }

    public function test_project_config_clamps_sample_rates(): void
    {
        $clean = ProjectConfig::sanitize([
            'sample' => ['traces' => ['request' => 5.0]],
        ]);
        $this->assertSame(1.0, $clean['sample']['traces']['request']);
    }

    public function test_project_stores_sparse_config_and_version(): void
    {
        $p = Project::create([
            'slug' => 'demo', 'name' => 'Demo', 'token' => 'tok-'.uniqid(), 'secret' => 'sek',
        ]);

        $this->assertSame([], $p->config ?? []);
        $this->assertSame(0, $p->config_version);

        $p->forceFill(['config' => ['host_interval' => 30], 'config_version' => 1])->save();

        $this->assertSame(30, $p->fresh()->config['host_interval']);
        $this->assertSame(1, $p->fresh()->config_version);
    }
}
