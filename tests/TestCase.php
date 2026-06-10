<?php

namespace VictorStochero\Warden\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use VictorStochero\Warden\WardenServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [WardenServiceProvider::class];
    }

    /** Subclasses override to boot as parent. */
    protected function observerMode(): string
    {
        return 'child';
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $this->test_connection());

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('warden.mode', $this->observerMode());
        $app['config']->set('warden.child.parent_url', 'https://parent.test');
        $app['config']->set('warden.child.project', 'demo');
        $app['config']->set('warden.child.token', 'test-token');
        $app['config']->set('warden.child.secret', 'test-secret');
        // Partitioning off for SQLite tests (DELETE fallback).
        $app['config']->set('warden.parent.partitioning', false);
        // Parent self-monitoring is opt-in per test (SelfMonitorTest enables it)
        // so the existing parent-mode suites keep their isolated fixtures.
        $app['config']->set('warden.parent.self_monitor', false);
    }

    /** @return array<string, mixed> */
    protected function test_connection(): array
    {
        $driver = getenv('DB_DRIVER') ?: 'sqlite';

        if ($driver === 'mysql' || $driver === 'mariadb') {
            return [
                'driver' => 'mysql',
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: '3306',
                'database' => getenv('DB_DATABASE') ?: 'observer_test',
                'username' => getenv('DB_USERNAME') ?: 'root',
                'password' => getenv('DB_PASSWORD') ?: '',
                'prefix' => '',
            ];
        }

        if ($driver === 'pgsql') {
            return [
                'driver' => 'pgsql',
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: '5432',
                'database' => getenv('DB_DATABASE') ?: 'observer_test',
                'username' => getenv('DB_USERNAME') ?: 'postgres',
                'password' => getenv('DB_PASSWORD') ?: '',
                'prefix' => '',
            ];
        }

        return ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
