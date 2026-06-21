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

    public function test_ensure_self_project_names_from_app_name(): void
    {
        config()->set('app.name', 'Acme APM');

        $project = (new ProjectManager)->ensureSelfProject('parent');

        $this->assertSame('Acme APM', $project->name);
        $this->assertSame('parent', $project->slug);
    }

    public function test_ensure_self_project_falls_back_to_headline_when_app_name_blank(): void
    {
        config()->set('app.name', '');

        $project = (new ProjectManager)->ensureSelfProject('parent');

        $this->assertSame('Parent', $project->name);
    }

    public function test_create_seeds_the_lean_capture_profile(): void
    {
        $project = (new ProjectManager)->create('My App')['project'];

        $this->assertSame('lean', $project->capture_profile);
        $this->assertSame(1, $project->config_version);
        $this->assertSame(100, $project->config['query']['capture_min_ms']);
        $this->assertSame(0.2, $project->config['sample']['traces']['request']);
        $this->assertFalse($project->config['sample']['type_gate']['cache']);
    }

    public function test_ensure_self_project_seeds_lean_on_first_create_only(): void
    {
        $manager = new ProjectManager;

        $first = $manager->ensureSelfProject('parent');
        $this->assertSame('lean', $first->capture_profile);

        // Simulate an operator who later widened capture; a second ensure (e.g.
        // on the next boot) must not clobber that back to lean.
        $first->forceFill(['capture_profile' => 'full', 'config' => null])->save();

        $again = $manager->ensureSelfProject('parent');
        $this->assertSame('full', $again->fresh()->capture_profile);
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
