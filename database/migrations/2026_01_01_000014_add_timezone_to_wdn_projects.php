<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->table('wdn_projects', function (Blueprint $table) {
            // Display timezone for absolute timestamps on the dashboard. Null =
            // use the parent app timezone (config('app.timezone')).
            $table->string('timezone', 64)->nullable()->after('audit_day');
        });
    }

    public function down(): void
    {
        Schema::connection()->table('wdn_projects', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
