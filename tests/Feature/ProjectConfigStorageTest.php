<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class ProjectConfigStorageTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
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
