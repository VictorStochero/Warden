<?php

namespace VictorStochero\Warden\Issues;

use Illuminate\Support\Carbon;
use VictorStochero\Warden\Models\Issue;
use VictorStochero\Warden\Models\IssueComment;

/**
 * Human-driven issue transitions (§5.3): resolve, ignore, reopen, assign and
 * snooze. The recurrence engine (IssueProcessor, §15) still auto-reopens a
 * resolved issue that fires again; this is the manual side a triager controls
 * from the dashboard. All writes hit the wdn connection, which the query
 * recorder ignores (§18.3), so no suppression wrapper is needed.
 */
class IssueWorkflow
{
    public function resolve(Issue $issue): void
    {
        $issue->forceFill([
            'status' => 'resolved',
            'resolved_at' => Carbon::now(),
            // Snapshot the release it was resolved on, so a later recurrence on a
            // newer release is recognised as a regression (§5.6).
            'resolved_release' => $issue->last_release,
        ])->save();
    }

    public function ignore(Issue $issue): void
    {
        $issue->forceFill(['status' => 'ignored'])->save();
    }

    public function reopen(Issue $issue): void
    {
        $issue->forceFill([
            'status' => 'open',
            'resolved_at' => null,
            'resolved_release' => null,
            'snoozed_until' => null,
        ])->save();
    }

    public function assign(Issue $issue, ?string $assignee): void
    {
        $assignee = $assignee !== null ? trim($assignee) : null;

        $issue->forceFill(['assignee' => $assignee === '' ? null : $assignee])->save();
    }

    /** Mute the issue from alerting for the given number of minutes. */
    public function snooze(Issue $issue, int $minutes): void
    {
        $minutes = max(1, $minutes);

        $issue->forceFill(['snoozed_until' => Carbon::now()->addMinutes($minutes)])->save();
    }

    /**
     * Append a triage note to the issue thread (§5.3). A blank body is a no-op
     * so an empty form submission never creates an empty row.
     */
    public function comment(Issue $issue, string $author, string $body): ?IssueComment
    {
        $body = trim($body);
        $author = trim($author);

        if ($body === '') {
            return null;
        }

        return IssueComment::query()->create([
            'issue_id' => $issue->id,
            'author' => $author === '' ? 'operator' : $author,
            'body' => $body,
            'created_at' => Carbon::now(),
        ]);
    }
}
