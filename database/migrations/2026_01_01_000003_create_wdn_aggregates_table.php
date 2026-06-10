<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->create('wdn_aggregates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id');
            $table->string('type', 48);
            $table->timestamp('bucket');                 // start of the rollup period
            $table->string('key', 191);                  // dimension within the type (route, query hash, queue...)
            $table->unsignedBigInteger('count')->default(0);
            $table->unsignedBigInteger('sum_duration')->default(0);
            $table->unsignedBigInteger('max_duration')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'type', 'bucket', 'key'], 'wdn_aggregates_unique');
            $table->index(['project_id', 'type', 'bucket'], 'wdn_aggregates_lookup');
        });
    }

    public function down(): void
    {
        Schema::connection()->dropIfExists('wdn_aggregates');
    }
};
