<?php

namespace VictorStochero\Warden\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;

/**
 * Per-project, per-step bookmark over wdn_events.id so consolidation is
 * incremental and idempotent across runs.
 */
class Cursor
{
    public function __construct(protected Connection $db) {}

    public function position(int $projectId, string $name): int
    {
        $row = $this->db->table('wdn_cursors')
            ->where('project_id', $projectId)
            ->where('name', $name)
            ->first();

        return Cast::int($row->position ?? null);
    }

    public function advance(int $projectId, string $name, int $position): void
    {
        $now = Carbon::now();

        $this->db->table('wdn_cursors')->updateOrInsert(
            ['project_id' => $projectId, 'name' => $name],
            ['position' => $position, 'updated_at' => $now, 'created_at' => $now],
        );
    }

    /**
     * Ensure the cursor row exists and lock it FOR UPDATE so concurrent rollups
     * of the same (project, step) serialize across hosts. Call inside a DB
     * transaction.
     */
    public function lock(int $projectId, string $name): void
    {
        $exists = $this->db->table('wdn_cursors')
            ->where('project_id', $projectId)
            ->where('name', $name)
            ->lockForUpdate()
            ->exists();

        if (! $exists) {
            // insertOrIgnore: on the very first run two hosts can both see the
            // row missing and race to create it; the unique (project_id, name)
            // constraint makes the loser a no-op instead of throwing.
            $now = Carbon::now();
            $this->db->table('wdn_cursors')->insertOrIgnore([
                'project_id' => $projectId,
                'name' => $name,
                'position' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
