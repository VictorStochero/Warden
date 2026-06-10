<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->create('wdn_incidents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id');
            $table->string('subject');                   // stable key for dedupe/cooldown
            $table->string('severity', 16)->default('warning'); // info | warning | critical
            $table->string('status', 16)->default('open');      // open | resolved
            $table->timestamp('started_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_alerted_at')->nullable();   // drives the cooldown window
            $table->text('summary')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status'], 'wdn_incidents_lookup');
            $table->index(['project_id', 'subject', 'status'], 'wdn_incidents_subject');
        });
    }

    public function down(): void
    {
        Schema::connection()->dropIfExists('wdn_incidents');
    }
};
