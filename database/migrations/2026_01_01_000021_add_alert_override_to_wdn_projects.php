<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->table('wdn_projects', function (Blueprint $table) {
            // Per-project override of the global e-mail alert settings. NULL on
            // any column means "inherit the global value".
            $table->boolean('alert_email_enabled')->nullable()->after('uptime_window');
            $table->text('alert_recipients')->nullable()->after('alert_email_enabled');
            $table->string('alert_min_severity')->nullable()->after('alert_recipients');
        });
    }

    public function down(): void
    {
        Schema::connection()->table('wdn_projects', function (Blueprint $table) {
            $table->dropColumn(['alert_email_enabled', 'alert_recipients', 'alert_min_severity']);
        });
    }
};
