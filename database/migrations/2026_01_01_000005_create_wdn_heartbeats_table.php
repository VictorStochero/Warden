<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->create('wdn_heartbeats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id');
            $table->string('key');                       // e.g. "schedule:run", "queue:default"
            $table->unsignedInteger('expected_interval'); // seconds
            $table->unsignedInteger('grace')->default(60); // seconds of tolerance
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('alerted')->default(false);  // currently in a missed state
            $table->timestamps();

            $table->unique(['project_id', 'key'], 'wdn_heartbeats_unique');
        });
    }

    public function down(): void
    {
        Schema::connection()->dropIfExists('wdn_heartbeats');
    }
};
