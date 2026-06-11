<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->table('wdn_events', function (Blueprint $table) {
            // Delivery (received_at grouping) was a full scan of the project's
            // partition; this covers the filter+group used by DashboardRepository.
            $table->index(['project_id', 'received_at'], 'wdn_events_project_received');
        });
    }

    public function down(): void
    {
        Schema::connection()->table('wdn_events', function (Blueprint $table) {
            $table->dropIndex('wdn_events_project_received');
        });
    }
};
