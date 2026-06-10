<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->table('wdn_events', function (Blueprint $table) {
            $table->index(['project_id', 'type', 'id'], 'wdn_events_project_type_id');
        });
    }

    public function down(): void
    {
        Schema::connection()->table('wdn_events', function (Blueprint $table) {
            $table->dropIndex('wdn_events_project_type_id');
        });
    }
};
