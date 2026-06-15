<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\Issue;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * The dashboard side of issue collaboration (§5.3): manage-gated POST actions to
 * resolve, ignore, reopen, assign and snooze an issue.
 */
class IssueActionsTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('viewWarden', fn ($u = null) => true);
        Gate::define('manageWarden', fn ($u = null) => true);
    }

    private function issue(string $status = 'open'): Issue
    {
        $project = Project::firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Demo', 'token' => 't', 'secret' => 's', 'active' => true]
        );

        return Issue::create([
            'project_id' => $project->id,
            'fingerprint' => 'fp-'.uniqid(),
            'class' => 'RuntimeException',
            'message' => 'boom',
            'count' => 1,
            'status' => $status,
        ]);
    }

    public function test_resolve_action_marks_resolved(): void
    {
        $issue = $this->issue();

        $this->post(route('warden.issue.resolve', ['demo', $issue->id]))
            ->assertRedirect(route('warden.issue', ['demo', $issue->id]));

        $this->assertSame('resolved', $issue->fresh()->status);
    }

    public function test_ignore_and_reopen_actions(): void
    {
        $issue = $this->issue();

        $this->post(route('warden.issue.ignore', ['demo', $issue->id]));
        $this->assertSame('ignored', $issue->fresh()->status);

        $this->post(route('warden.issue.reopen', ['demo', $issue->id]));
        $this->assertSame('open', $issue->fresh()->status);
    }

    public function test_assign_action_sets_the_assignee(): void
    {
        $issue = $this->issue();

        $this->post(route('warden.issue.assign', ['demo', $issue->id]), ['assignee' => 'ana@team.test']);

        $this->assertSame('ana@team.test', $issue->fresh()->assignee);
    }

    public function test_snooze_action_mutes_the_issue(): void
    {
        $issue = $this->issue();

        $this->post(route('warden.issue.snooze', ['demo', $issue->id]), ['minutes' => 30]);

        $this->assertTrue($issue->fresh()->isSnoozed());
    }

    public function test_actions_require_manage_ability(): void
    {
        Gate::define('manageWarden', fn ($u = null) => false);
        $issue = $this->issue();

        $this->post(route('warden.issue.resolve', ['demo', $issue->id]))->assertForbidden();
    }

    public function test_issue_page_renders_collaboration_controls_for_managers(): void
    {
        $issue = $this->issue();

        $this->get(route('warden.issue', ['demo', $issue->id]))
            ->assertOk()
            ->assertSee(__('warden::issues.actions.resolve'))
            ->assertSee(route('warden.issue.assign', ['demo', $issue->id]));
    }
}
