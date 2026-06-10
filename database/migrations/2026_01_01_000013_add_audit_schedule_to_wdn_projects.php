<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->table('wdn_projects', function (Blueprint $table) {
            // Intuitive audit schedule. The parent advertises "audit due" on the
            // ingest response when the configured period elapses; the child's
            // shipper then runs warden:audit.
            //   audit_frequency: off | daily | weekly | monthly
            //   audit_day:       weekly 0-6 (Sun-Sat) / monthly 1-31 / null = any
            $table->string('audit_frequency', 16)->default('off')->after('last_seen_at');
            $table->unsignedTinyInteger('audit_day')->nullable()->after('audit_frequency');
        });
    }

    public function down(): void
    {
        Schema::connection()->table('wdn_projects', function (Blueprint $table) {
            $table->dropColumn(['audit_frequency', 'audit_day']);
        });
    }
};
