<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->table('wdn_projects', function (Blueprint $table) {
            // Preferred hour of day (0-23, in the project timezone) for scheduled
            // audits; null = any time once the period elapses.
            $table->unsignedTinyInteger('audit_hour')->nullable()->after('audit_day');
            // Set by "Run audit now"; makes the next ingest advertise audit_due
            // until a fresh security snapshot is received.
            $table->timestamp('audit_requested_at')->nullable()->after('audit_hour');
            // KPI window for the project's Uptime section: 24h | 7d | 30d.
            $table->string('uptime_window', 8)->default('30d')->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::connection()->table('wdn_projects', function (Blueprint $table) {
            $table->dropColumn(['audit_hour', 'audit_requested_at', 'uptime_window']);
        });
    }
};
