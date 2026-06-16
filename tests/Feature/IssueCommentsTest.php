<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Issues\IssueWorkflow;
use VictorStochero\Warden\Models\Issue;
use VictorStochero\Warden\Models\IssueComment;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Issue comments (§5.3): the triage thread that completes issue collaboration.
 * A manager leaves a note on an issue; it persists with the author and reads
 * back in chronological order.
 */
class IssueCommentsTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function issue(): Issue
    {
        $project = Project::firstOrCreate(['slug' => 'demo'], ['name' => 'Demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        return Issue::query()->create([
            'project_id' => $project->id,
            'fingerprint' => 'abc123',
            'class' => 'RuntimeException',
            'message' => 'boom',
            'count' => 1,
            'users_affected' => 0,
            'status' => 'open',
        ]);
    }

    public function test_a_comment_is_persisted_with_its_author(): void
    {
        $issue = $this->issue();

        $this->app->make(IssueWorkflow::class)->comment($issue, 'alice@acme.test', 'Looking into it.');

        $this->assertDatabaseHas('wdn_issue_comments', [
            'issue_id' => $issue->id,
            'author' => 'alice@acme.test',
            'body' => 'Looking into it.',
        ]);
    }

    public function test_a_blank_comment_is_rejected(): void
    {
        $issue = $this->issue();

        $this->app->make(IssueWorkflow::class)->comment($issue, 'alice@acme.test', '   ');

        $this->assertSame(0, IssueComment::query()->count());
    }

    public function test_comments_read_back_in_chronological_order(): void
    {
        $issue = $this->issue();
        $workflow = $this->app->make(IssueWorkflow::class);

        $workflow->comment($issue, 'alice@acme.test', 'first');
        $workflow->comment($issue, 'bob@acme.test', 'second');

        $bodies = IssueComment::query()
            ->where('issue_id', $issue->id)
            ->orderBy('id')
            ->pluck('body')
            ->all();

        $this->assertSame(['first', 'second'], $bodies);
    }

    public function test_posting_a_comment_requires_manage_permission(): void
    {
        config()->set('warden.dashboard.auth.mode', 'gate');
        $issue = $this->issue();

        // No manageWarden gate granted in the testbench app → 403.
        $this->post(route('warden.issue.comment', ['demo', $issue->id]), ['body' => 'hi'])
            ->assertForbidden();

        $this->assertSame(0, IssueComment::query()->count());
    }
}
