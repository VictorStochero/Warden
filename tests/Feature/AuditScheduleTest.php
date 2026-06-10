<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Covers IngestController::auditDue() under the frequency/day/hour scheduling
 * model. We drive it through the public ingest endpoint, seeding a `security`
 * event with a controlled received_at to represent "the last audit landed then".
 */
class AuditScheduleTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function project(): Project
    {
        return Project::create([
            'name' => 'Demo', 'slug' => 'demo',
            'token' => 'ptoken', 'secret' => 'psecret', 'active' => true,
            'timezone' => 'UTC',
        ]);
    }

    /** A byte-exact signed body the ingest endpoint will accept. */
    protected function signedBody(): string
    {
        return (string) json_encode([
            'schema_version' => 2, 'project' => 'demo', 'sent_at' => Carbon::now()->getTimestamp(),
            'batches' => [['id' => 'b'.bin2hex(random_bytes(5)), 'events' => []]],
        ], JSON_UNESCAPED_SLASHES);
    }

    protected function postIngest(): TestResponse
    {
        $body = $this->signedBody();
        $server = $this->transformHeadersToServerVars([
            'X-Warden-Token' => 'ptoken',
            'X-Warden-Signature' => hash_hmac('sha256', $body, 'psecret'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        return $this->call('POST', route('warden.ingest'), [], [], [], $server, $body);
    }

    protected function seedSecurityEvent(Project $project, Carbon $receivedAt): void
    {
        DB::table('wdn_events')->insert([
            'project_id' => $project->id,
            'type' => 'security',
            'occurred_at' => $receivedAt->toDateTimeString(),
            'occurred_date' => $receivedAt->toDateString(),
            'received_at' => $receivedAt->toDateTimeString(),
            'payload' => '{}',
        ]);
    }

    public function test_off_is_never_due(): void
    {
        $this->project(); // audit_frequency defaults to off
        $this->postIngest()->assertStatus(202)->assertJson(['audit_due' => false]);
    }

    public function test_daily_is_due_when_the_last_audit_predates_today_slot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-09 15:00:00', 'UTC'));
        $project = $this->project();
        $project->update(['audit_frequency' => 'daily', 'audit_hour' => 9]);

        // Last audit yesterday -> today's 09:00 slot has fired since -> due.
        $this->seedSecurityEvent($project, Carbon::parse('2026-06-08 09:30:00', 'UTC'));
        $this->postIngest()->assertStatus(202)->assertJson(['audit_due' => true]);

        Carbon::setTestNow();
    }

    public function test_daily_is_not_due_when_the_last_audit_is_after_today_slot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-09 15:00:00', 'UTC'));
        $project = $this->project();
        $project->update(['audit_frequency' => 'daily', 'audit_hour' => 9]);

        // Audit landed at 09:05 today, after the 09:00 slot -> not due yet.
        $this->seedSecurityEvent($project, Carbon::parse('2026-06-09 09:05:00', 'UTC'));
        $this->postIngest()->assertStatus(202)->assertJson(['audit_due' => false]);

        Carbon::setTestNow();
    }

    public function test_daily_is_not_due_before_the_hour_has_arrived_today(): void
    {
        // 08:00 now, slot is 09:00 -> the most recent boundary is yesterday 09:00.
        Carbon::setTestNow(Carbon::parse('2026-06-09 08:00:00', 'UTC'));
        $project = $this->project();
        $project->update(['audit_frequency' => 'daily', 'audit_hour' => 9]);

        // Last audit yesterday at 10:00 (after yesterday's 09:00 slot) -> not due.
        $this->seedSecurityEvent($project, Carbon::parse('2026-06-08 10:00:00', 'UTC'));
        $this->postIngest()->assertStatus(202)->assertJson(['audit_due' => false]);

        Carbon::setTestNow();
    }

    public function test_weekly_respects_the_configured_weekday(): void
    {
        // 2026-06-09 is a Tuesday (dayOfWeek 2). Schedule for Monday (1).
        Carbon::setTestNow(Carbon::parse('2026-06-09 12:00:00', 'UTC'));
        $project = $this->project();
        $project->update(['audit_frequency' => 'weekly', 'audit_day' => 1, 'audit_hour' => 6]);

        // Monday 2026-06-08 06:00 boundary; last audit the prior week -> due.
        $this->seedSecurityEvent($project, Carbon::parse('2026-06-02 06:00:00', 'UTC'));
        $this->postIngest()->assertStatus(202)->assertJson(['audit_due' => true]);

        // Audit landed after Monday's boundary -> not due.
        DB::table('wdn_events')->where('project_id', $project->id)->delete();
        $this->seedSecurityEvent($project, Carbon::parse('2026-06-08 07:00:00', 'UTC'));
        $this->postIngest()->assertStatus(202)->assertJson(['audit_due' => false]);

        Carbon::setTestNow();
    }

    public function test_monthly_respects_the_configured_day_of_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-09 12:00:00', 'UTC'));
        $project = $this->project();
        $project->update(['audit_frequency' => 'monthly', 'audit_day' => 1, 'audit_hour' => 0]);

        // Boundary is 2026-06-01 00:00; last audit in May -> due.
        $this->seedSecurityEvent($project, Carbon::parse('2026-05-15 00:00:00', 'UTC'));
        $this->postIngest()->assertStatus(202)->assertJson(['audit_due' => true]);

        // Audit landed on the 2nd, after the boundary -> not due.
        DB::table('wdn_events')->where('project_id', $project->id)->delete();
        $this->seedSecurityEvent($project, Carbon::parse('2026-06-02 00:00:00', 'UTC'));
        $this->postIngest()->assertStatus(202)->assertJson(['audit_due' => false]);

        Carbon::setTestNow();
    }

    public function test_instant_request_wins_over_a_recent_audit(): void
    {
        $project = $this->project();
        // Even with no schedule, a fresh "run now" request is due until answered.
        $project->update(['audit_requested_at' => now()]);
        $this->postIngest()->assertStatus(202)->assertJson(['audit_due' => true]);
    }
}
