<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->table('wdn_issues', function (Blueprint $table): void {
            // Collaboration workflow (§5.3): when an issue was resolved (for the
            // "resolved · reopened on recurrence" trail) and a snooze window that
            // mutes it from alerting until a chosen time.
            $table->timestamp('resolved_at')->nullable()->after('status');
            $table->timestamp('snoozed_until')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::connection()->table('wdn_issues', function (Blueprint $table): void {
            $table->dropColumn(['resolved_at', 'snoozed_until']);
        });
    }
};
