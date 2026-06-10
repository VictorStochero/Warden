<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Carbon;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Support\Schema;
use VictorStochero\Warden\Tests\TestCase;

class PruneBatchesTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    public function test_prune_removes_only_expired_ingested_batches(): void
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        Schema::db()->table('wdn_ingested_batches')->insert([
            ['project_id' => $project->id, 'batch_id' => 'old', 'received_at' => Carbon::now()->subDays(30)],
            ['project_id' => $project->id, 'batch_id' => 'fresh', 'received_at' => Carbon::now()],
        ]);

        $this->artisan('warden:prune')->assertSuccessful();

        $remaining = Schema::db()->table('wdn_ingested_batches')->pluck('batch_id')->all();
        $this->assertSame(['fresh'], $remaining);
    }
}
