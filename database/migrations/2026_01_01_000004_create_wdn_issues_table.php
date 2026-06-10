<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->create('wdn_issues', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id');
            $table->string('fingerprint', 64);
            $table->string('class');
            $table->text('message');
            $table->string('last_trace_id', 32)->nullable();
            $table->unsignedBigInteger('count')->default(0);
            $table->unsignedBigInteger('users_affected')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('status', 16)->default('open'); // open | resolved | ignored
            $table->string('priority', 16)->nullable();
            $table->string('assignee')->nullable();
            $table->json('stack')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'fingerprint'], 'wdn_issues_unique');
            $table->index(['project_id', 'status', 'last_seen_at'], 'wdn_issues_lookup');
        });
    }

    public function down(): void
    {
        Schema::connection()->dropIfExists('wdn_issues');
    }
};
