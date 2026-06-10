<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Maintenance\RunMaintenanceJob;
use VictorStochero\Warden\Models\CommandRun;
use VictorStochero\Warden\Models\DeadLetter;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class MaintenanceTriggerTest extends TestCase
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

    public function test_job_runs_allowed_command_and_marks_ok(): void
    {
        $run = CommandRun::create(['command' => 'prune', 'status' => 'queued', 'queued_at' => now()]);

        (new RunMaintenanceJob('prune', (int) $run->id))->handle();

        $fresh = $run->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('ok', $fresh->status);
        $this->assertNotNull($fresh->finished_at);
    }

    public function test_job_rejects_command_outside_whitelist(): void
    {
        $run = CommandRun::create(['command' => 'migrate', 'status' => 'queued', 'queued_at' => now()]);

        (new RunMaintenanceJob('migrate', (int) $run->id))->handle();

        $fresh = $run->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('failed', $fresh->status);
    }

    public function test_run_endpoint_dispatches_job_and_records_run(): void
    {
        Bus::fake();

        $this->post(route('warden.admin.maintenance.run'), ['command' => 'aggregate'])
            ->assertRedirect(route('warden.admin.maintenance'));

        $this->assertSame(1, CommandRun::where('command', 'aggregate')->where('status', 'queued')->count());
        Bus::assertDispatched(RunMaintenanceJob::class);
    }

    public function test_run_endpoint_rejects_unknown_command(): void
    {
        Bus::fake();

        $this->post(route('warden.admin.maintenance.run'), ['command' => 'migrate'])
            ->assertRedirect(route('warden.admin.maintenance'));

        $this->assertSame(0, CommandRun::count());
        Bus::assertNothingDispatched();
    }

    public function test_maintenance_endpoint_is_gated_by_manage_ability(): void
    {
        Gate::define('manageWarden', fn ($u = null) => false);

        $this->post(route('warden.admin.maintenance.run'), ['command' => 'aggregate'])->assertForbidden();
    }

    public function test_maintenance_page_lists_recent_dead_letters(): void
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);
        DeadLetter::create([
            'project_id' => $project->id, 'batch_id' => 'b-xyz', 'reason' => 'max_attempts_exceeded',
            'attempts' => 10, 'reported_at' => now(),
        ]);

        $this->get(route('warden.admin.maintenance'))->assertOk()->assertSee('b-xyz');
    }
}
