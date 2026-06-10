<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    /**
     * Raw, high-churn event stream. This portable definition works on every
     * driver (and is the SQLite test fallback). On MySQL/PostgreSQL the
     * `warden:partition` command converts this into a RANGE-partitioned
     * table keyed on `occurred_date`, after which `warden:prune` drops whole
     * partitions instead of issuing DELETEs (§18.5).
     */
    public function up(): void
    {
        Schema::connection()->create('wdn_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id');
            $table->string('type', 32);
            $table->string('trace_id', 32)->nullable();
            $table->string('span_id', 32)->nullable();
            $table->string('parent_span_id', 32)->nullable();
            $table->timestamp('occurred_at', 6);
            $table->date('occurred_date');               // partition key (filled at ingest)
            $table->timestamp('received_at')->nullable(); // parent-side, accounts for clock skew
            $table->unsignedBigInteger('duration_us')->nullable();
            $table->json('payload')->nullable();

            $table->index(['project_id', 'type', 'occurred_at'], 'wdn_events_project_type_time');
            $table->index(['project_id', 'trace_id'], 'wdn_events_project_trace');
        });
    }

    public function down(): void
    {
        Schema::connection()->dropIfExists('wdn_events');
    }
};
