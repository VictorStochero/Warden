<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->table('wdn_projects', function (Blueprint $table) {
            // Sparse, admin-set behaviour overrides pushed to the child via the
            // ingest control channel. Null = child uses its own .env/defaults.
            $table->json('config')->nullable()->after('alert_min_severity');
            $table->unsignedInteger('config_version')->default(0)->after('config');
        });
    }

    public function down(): void
    {
        Schema::connection()->table('wdn_projects', function (Blueprint $table) {
            $table->dropColumn(['config', 'config_version']);
        });
    }
};
