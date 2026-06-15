<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Release/deploy tracking (§5.6), parent side: the ingestor persists the
 * per-event release marker into wdn_events so it can be queried.
 */
class ReleaseIngestTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    public function test_ingested_events_store_their_release(): void
    {
        Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        $at = now()->format('Y-m-d H:i:s.u');
        $this->app->make(Ingestor::class)->ingest('demo', [['id' => 'b1', 'events' => [
            ['type' => 'exception', 'trace_id' => 't1', 'occurred_at' => $at, 'release' => 'v9.9.9', 'payload' => ['class' => 'E', 'message' => 'boom']],
        ]]]);

        $row = DB::table('wdn_events')->where('type', 'exception')->first();

        $this->assertNotNull($row);
        $this->assertSame('v9.9.9', $row->release);
    }
}
