<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->create('wdn_alert_rules', function (Blueprint $table): void {
            // UI-managed threshold rules (§5.5) — evaluated alongside the
            // config-defined warden.alerts.rules by the Evaluator.
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('metric', 32);    // error_rate | p95 | throughput | …
            $table->string('op', 2);         // > | >= | < | <=
            $table->double('threshold');
            $table->string('window', 8);     // 15m | 1h | 6h | 24h | 7d
            $table->string('severity', 16);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique('name', 'wdn_alert_rules_name');
        });
    }

    public function down(): void
    {
        Schema::connection()->dropIfExists('wdn_alert_rules');
    }
};
