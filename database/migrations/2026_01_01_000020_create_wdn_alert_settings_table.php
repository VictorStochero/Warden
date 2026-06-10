<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->create('wdn_alert_settings', function (Blueprint $table) {
            // Single global row holding the e-mail alert channel configuration,
            // editable from the dashboard (Settings -> Alerts).
            $table->bigIncrements('id');
            $table->boolean('email_enabled')->default(false);
            $table->text('recipients')->nullable();
            $table->string('min_severity')->default('warning');
            $table->unsignedInteger('cooldown')->default(300);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection()->dropIfExists('wdn_alert_settings');
    }
};
