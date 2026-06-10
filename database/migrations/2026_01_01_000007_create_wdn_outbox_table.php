<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    /**
     * Child-side staging table. Batches captured during a request/command are
     * persisted here on terminate; `warden:ship` drains them to the parent.
     * Lives on the child app's own database — never the parent's.
     */
    public function up(): void
    {
        Schema::connection()->create('wdn_outbox', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->json('batch');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable(); // backoff gate
            $table->timestamp('reserved_at')->nullable();  // in-flight marker for the daemon
            $table->timestamps();

            $table->index(['available_at', 'reserved_at'], 'wdn_outbox_drain');
        });
    }

    public function down(): void
    {
        Schema::connection()->dropIfExists('wdn_outbox');
    }
};
