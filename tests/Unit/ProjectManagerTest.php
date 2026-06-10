<?php

namespace VictorStochero\Warden\Tests\Unit;

use VictorStochero\Warden\Projects\ProjectManager;
use VictorStochero\Warden\Tests\TestCase;

class ProjectManagerTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    public function test_create_mints_credentials_and_slug(): void
    {
        $result = (new ProjectManager)->create('My App');

        $this->assertSame('my-app', $result['project']->slug);
        $this->assertSame(40, strlen($result['token']));
        $this->assertSame(64, strlen($result['secret']));
    }

    public function test_duplicate_slug_throws(): void
    {
        $manager = new ProjectManager;
        $manager->create('My App');

        $this->expectException(\RuntimeException::class);
        $manager->create('My App');
    }

    public function test_rotate_changes_token_and_secret(): void
    {
        $manager = new ProjectManager;
        $created = $manager->create('My App');
        $oldToken = $created['project']->token;

        $rotated = $manager->rotate($created['project']->fresh());

        $this->assertNotSame($oldToken, $rotated['token']);
        $this->assertSame(40, strlen($rotated['token']));
    }

    public function test_install_command_includes_flags(): void
    {
        $cmd = (new ProjectManager)->installCommand('my-app', 'tok', 'sec', 'https://apm.example.com', 'daemon');

        $this->assertStringContainsString('warden:install --child', $cmd);
        $this->assertStringContainsString('--project=my-app', $cmd);
        $this->assertStringContainsString('--parent-url=https://apm.example.com', $cmd);
        $this->assertStringContainsString('--delivery=daemon', $cmd);
    }
}
