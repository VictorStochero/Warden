<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->table('wdn_projects', function (Blueprint $table): void {
            // Per-project retention (§5.12). Null = inherit the global window. A
            // value only TIGHTENS below the global ceiling (the global prune /
            // DROP PARTITION is the storage maximum), for privacy / cost control.
            $table->unsignedSmallInteger('raw_retention_days')->nullable()->after('uptime_window');
            $table->unsignedSmallInteger('aggregate_retention_days')->nullable()->after('raw_retention_days');
        });
    }

    public function down(): void
    {
        Schema::connection()->table('wdn_projects', function (Blueprint $table): void {
            $table->dropColumn(['raw_retention_days', 'aggregate_retention_days']);
        });
    }
};
