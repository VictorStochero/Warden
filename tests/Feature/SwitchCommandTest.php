<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use VictorStochero\Warden\Tests\TestCase;

class SwitchCommandTest extends TestCase
{
    private string $envPath;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Self-monitoring on so the parent switch ensures the self project.
        $app['config']->set('warden.parent.self_monitor', true);

        $this->envPath = sys_get_temp_dir().'/wdn_switch_'.uniqid().'.env';
        file_put_contents($this->envPath, "APP_NAME=Demo\nWARDEN_MODE=child\n");
        $app->useEnvironmentPath(dirname($this->envPath));
        $app->loadEnvironmentFrom(basename($this->envPath));
    }

    protected function tearDown(): void
    {
        @unlink($this->envPath);
        @unlink(public_path('vendor/warden/warden.css'));
        @rmdir(public_path('vendor/warden'));
        parent::tearDown();
    }

    public function test_switch_to_parent_rewrites_mode_and_rebuilds_schema(): void
    {
        // Sentinel row: it must be gone after the schema is rebuilt from zero.
        DB::table('wdn_cursors')->insert([
            'project_id' => 1, 'name' => 'sentinel', 'position' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('warden:switch', ['mode' => 'parent', '--force' => true])
            ->assertSuccessful();

        $this->assertStringContainsString('WARDEN_MODE=parent', (string) file_get_contents($this->envPath));

        // Tables exist again (migrate re-ran) and the sentinel is gone (dropped + recreated).
        $this->assertTrue(Schema::hasTable('wdn_cursors'));
        $this->assertTrue(Schema::hasTable('wdn_events'));
        $this->assertSame(0, DB::table('wdn_cursors')->count());

        // Parent self project ensured.
        $this->assertTrue(DB::table('wdn_projects')->where('slug', 'parent')->exists());

        // The dashboard stylesheet is published for the parent.
        $this->assertFileExists(public_path('vendor/warden/warden.css'));
    }

    public function test_switch_to_child_writes_credentials(): void
    {
        // Booted as child; switch to parent first so child is a real transition.
        $this->artisan('warden:switch', ['mode' => 'parent', '--force' => true])->assertSuccessful();
        $this->app['config']->set('warden.mode', 'parent');

        $this->artisan('warden:switch', [
            'mode' => 'child',
            '--parent-url' => 'https://apm.example.com',
            '--token' => 'tok123',
            '--secret' => 'sec123',
            '--force' => true,
        ])->assertSuccessful();

        $env = (string) file_get_contents($this->envPath);
        $this->assertStringContainsString('WARDEN_MODE=child', $env);
        $this->assertStringContainsString('WARDEN_PARENT_URL=https://apm.example.com', $env);
        $this->assertStringContainsString('WARDEN_TOKEN=tok123', $env);
        $this->assertTrue(Schema::hasTable('wdn_events'));

        // Switching to child removes the parent-only dashboard stylesheet.
        $this->assertFileDoesNotExist(public_path('vendor/warden/warden.css'));
    }

    public function test_switching_to_the_current_mode_is_a_noop(): void
    {
        $this->artisan('warden:switch', ['mode' => 'child'])
            ->expectsOutputToContain('already in child mode')
            ->assertSuccessful();
    }

    public function test_rejects_an_invalid_mode(): void
    {
        $this->artisan('warden:switch', ['mode' => 'banana', '--force' => true])
            ->assertFailed();
    }

    public function test_declining_the_confirmation_aborts(): void
    {
        $this->artisan('warden:switch', ['mode' => 'parent'])
            ->expectsConfirmation('Continue?', 'no')
            ->assertFailed();

        // Untouched: still child, tables intact.
        $this->assertStringContainsString('WARDEN_MODE=child', (string) file_get_contents($this->envPath));
        $this->assertTrue(Schema::hasTable('wdn_events'));
    }
}
