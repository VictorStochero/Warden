<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Tests\TestCase;

class InstallCommandTest extends TestCase
{
    private string $envPath;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->envPath = sys_get_temp_dir().'/wdn_install_'.uniqid().'.env';
        file_put_contents($this->envPath, "APP_NAME=Demo\n");
        $app->useEnvironmentPath(dirname($this->envPath));
        $app->loadEnvironmentFrom(basename($this->envPath));
    }

    protected function tearDown(): void
    {
        @unlink($this->envPath);
        parent::tearDown();
    }

    public function test_child_install_writes_mode_and_credentials(): void
    {
        $this->artisan('warden:install', [
            '--child' => true,
            '--parent-url' => 'https://apm.example.com',
            '--project' => 'my-app',
            '--token' => 'tok123',
            '--secret' => 'sec123',
            '--no-migrate' => true,
        ])->assertSuccessful();

        $env = (string) file_get_contents($this->envPath);
        $this->assertStringContainsString('WARDEN_MODE=child', $env);
        $this->assertStringContainsString('WARDEN_PARENT_URL=https://apm.example.com', $env);
        $this->assertStringContainsString('WARDEN_PROJECT=my-app', $env);
        $this->assertStringContainsString('WARDEN_TOKEN=tok123', $env);
        $this->assertStringContainsString('WARDEN_SECRET=sec123', $env);
        $this->assertStringContainsString('APP_NAME=Demo', $env);
    }

    public function test_parent_install_writes_mode(): void
    {
        $this->artisan('warden:install', ['--parent' => true, '--no-migrate' => true])
            ->assertSuccessful();

        $this->assertStringContainsString('WARDEN_MODE=parent', (string) file_get_contents($this->envPath));
    }

    public function test_passing_both_flags_fails(): void
    {
        $this->artisan('warden:install', ['--parent' => true, '--child' => true, '--no-migrate' => true])
            ->assertFailed();
    }
}
