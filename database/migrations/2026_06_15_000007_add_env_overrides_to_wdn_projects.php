<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->table('wdn_projects', function (Blueprint $table) {
            // Knobs whose value is pinned by the child's own .env (precedence
            // .env > dashboard > default). Reported by the child on every ingest
            // so the dashboard can honestly flag a toggle as ignored locally.
            // Null = child has not reported yet.
            $table->json('env_overrides')->nullable()->after('config_version');
        });
    }

    public function down(): void
    {
        Schema::connection()->table('wdn_projects', function (Blueprint $table) {
            $table->dropColumn('env_overrides');
        });
    }
};
