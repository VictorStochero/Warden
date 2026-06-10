<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use VictorStochero\Warden\Tests\TestCase;

class UninstallCommandTest extends TestCase
{
    private string $envPath;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->envPath = sys_get_temp_dir().'/wdn_uninstall_'.uniqid().'.env';
        file_put_contents(
            $this->envPath,
            "APP_NAME=Demo\nWARDEN_MODE=parent\nWARDEN_DASHBOARD_PASSWORD=secret\nWARDEN_SAMPLE_REQUEST=1.0\n",
        );
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

    public function test_uninstall_drops_tables_and_strips_env(): void
    {
        $this->assertTrue(Schema::hasTable('wdn_events'));

        $this->artisan('warden:uninstall', ['--force' => true])
            ->assertSuccessful();

        // Every wdn_ table is gone.
        $this->assertFalse(Schema::hasTable('wdn_events'));
        $this->assertFalse(Schema::hasTable('wdn_projects'));

        // No WARDEN_* trace left, untouched keys preserved.
        $env = (string) file_get_contents($this->envPath);
        $this->assertStringNotContainsString('WARDEN_', $env);
        $this->assertStringContainsString('APP_NAME=Demo', $env);
    }

    public function test_uninstall_removes_the_published_stylesheet(): void
    {
        $css = public_path('vendor/warden/warden.css');
        @mkdir(dirname($css), 0777, true);
        file_put_contents($css, '/* warden */');
        $this->assertFileExists($css);

        $this->artisan('warden:uninstall', ['--force' => true])->assertSuccessful();

        $this->assertFileDoesNotExist($css);
    }

    public function test_declining_the_confirmation_aborts(): void
    {
        $this->artisan('warden:uninstall')
            ->expectsConfirmation('Continue?', 'no')
            ->assertFailed();

        // Tables and .env untouched.
        $this->assertTrue(Schema::hasTable('wdn_events'));
        $this->assertStringContainsString('WARDEN_MODE=parent', (string) file_get_contents($this->envPath));
    }
}
