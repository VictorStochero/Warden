<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->create('wdn_ingested_batches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id');
            $table->string('batch_id', 64);
            $table->timestamp('received_at');
            $table->unique(['project_id', 'batch_id'], 'wdn_ingested_batches_unique');
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::connection()->dropIfExists('wdn_ingested_batches');
    }
};
