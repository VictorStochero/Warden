<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use VictorStochero\Warden\Config\SelfMonitorConfig;
use VictorStochero\Warden\Tests\TestCase;

class SelfMonitorConfigTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    public function test_applies_own_project_sparse_config(): void
    {
        DB::table('wdn_projects')->insert([
            'slug' => 'parent', 'name' => 'Parent', 'token' => 'tok', 'secret' => 'sek',
            'active' => true, 'config' => json_encode(['host_interval' => 77]),
            'config_version' => 2, 'created_at' => now(), 'updated_at' => now(),
        ]);

        config()->set('warden.parent.self_project', 'parent');
        config()->set('warden.child.host_interval', 15);

        (new SelfMonitorConfig)->apply($this->app['config']);

        $this->assertSame(77, config('warden.child.host_interval'));
    }

    public function test_no_op_when_self_project_has_no_config(): void
    {
        DB::table('wdn_projects')->insert([
            'slug' => 'parent', 'name' => 'Parent', 'token' => 'tok', 'secret' => 'sek',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        config()->set('warden.parent.self_project', 'parent');
        config()->set('warden.child.host_interval', 15);

        (new SelfMonitorConfig)->apply($this->app['config']);

        $this->assertSame(15, config('warden.child.host_interval'));
    }
}
