<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    /**
     * Bookmarks the last raw event consumed by each consolidation step
     * (aggregation, issues) per project, so rollups are incremental and never
     * double-count under repeated runs.
     */
    public function up(): void
    {
        Schema::connection()->create('wdn_cursors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id');
            $table->string('name');                  // e.g. "aggregate:query", "issues"
            $table->unsignedBigInteger('position')->default(0); // last wdn_events.id consumed
            $table->timestamps();

            $table->unique(['project_id', 'name'], 'wdn_cursors_unique');
        });
    }

    public function down(): void
    {
        Schema::connection()->dropIfExists('wdn_cursors');
    }
};
