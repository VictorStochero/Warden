<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Alerting\Evaluator;
use VictorStochero\Warden\Issues\IssueWorkflow;
use VictorStochero\Warden\Models\Incident;
use VictorStochero\Warden\Models\Issue;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Issue collaboration workflow (§5.3): the status/assignee/snooze transitions
 * a human drives from the dashboard — the part of "issues" that Telescope and
 * Pulse don't have at all.
 */
class IssueWorkflowTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
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

    private function workflow(): IssueWorkflow
    {
        return $this->app->make(IssueWorkflow::class);
    }

    public function test_resolve_marks_resolved_and_stamps_the_time(): void
    {
        $issue = $this->issue();

        $this->workflow()->resolve($issue);

        $this->assertSame('resolved', $issue->fresh()->status);
        $this->assertNotNull($issue->fresh()->resolved_at);
    }

    public function test_ignore_marks_ignored(): void
    {
        $issue = $this->issue();

        $this->workflow()->ignore($issue);

        $this->assertSame('ignored', $issue->fresh()->status);
    }

    public function test_reopen_clears_resolution_and_snooze(): void
    {
        $issue = $this->issue('resolved');
        $this->workflow()->snooze($issue, 60);
        $this->workflow()->resolve($issue);

        $this->workflow()->reopen($issue);

        $fresh = $issue->fresh();
        $this->assertSame('open', $fresh->status);
        $this->assertNull($fresh->resolved_at);
        $this->assertNull($fresh->snoozed_until);
    }

    public function test_assign_sets_and_clears_the_assignee(): void
    {
        $issue = $this->issue();

        $this->workflow()->assign($issue, 'ana@team.test');
        $this->assertSame('ana@team.test', $issue->fresh()->assignee);

        $this->workflow()->assign($issue, null);
        $this->assertNull($issue->fresh()->assignee);
    }

    public function test_snooze_mutes_the_issue_until_a_future_time(): void
    {
        $issue = $this->issue();

        $this->workflow()->snooze($issue, 120);

        $fresh = $issue->fresh();
        $this->assertTrue($fresh->isSnoozed());
        $this->assertTrue($fresh->snoozed_until->isFuture());
    }

    public function test_a_snoozed_issue_does_not_open_an_incident(): void
    {
        $issue = $this->issue();
        $this->workflow()->snooze($issue, 120);

        $this->app->make(Evaluator::class)->evaluate($issue->project_id);

        $this->assertSame(
            0,
            Incident::query()->where('subject', 'issue:'.$issue->fingerprint)->count(),
            'A snoozed issue must not raise a fresh incident/alert'
        );
    }

    public function test_an_open_issue_still_opens_an_incident(): void
    {
        $issue = $this->issue();

        $this->app->make(Evaluator::class)->evaluate($issue->project_id);

        $this->assertSame(
            1,
            Incident::query()->where('subject', 'issue:'.$issue->fingerprint)->count(),
            'A plain open issue must still raise its incident'
        );
    }
}
