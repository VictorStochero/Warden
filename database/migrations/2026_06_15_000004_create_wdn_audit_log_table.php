<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->create('wdn_audit_log', function (Blueprint $table): void {
            // Who did what in the dashboard (§5.7) — every successful manage
            // action, for an accountability trail in a multi-operator parent.
            $table->bigIncrements('id');
            $table->string('actor');                 // resolved identity (email / role)
            $table->string('action');                // route name, e.g. warden.admin.projects.store
            $table->string('target')->nullable();    // route params (project slug, issue id, …)
            $table->string('method', 8);
            $table->string('ip', 45)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at', 'wdn_audit_log_time');
        });
    }

    public function down(): void
    {
        Schema::connection()->dropIfExists('wdn_audit_log');
    }
};
