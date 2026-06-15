<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->table('wdn_events', function (Blueprint $table): void {
            // Release/deploy marker stamped by the child (§5.6), so the parent can
            // answer "errors since this deploy" and detect a regression after one.
            $table->string('release', 64)->nullable()->after('payload');
            $table->index(['project_id', 'type', 'release'], 'wdn_events_release');
        });
    }

    public function down(): void
    {
        Schema::connection()->table('wdn_events', function (Blueprint $table): void {
            $table->dropIndex('wdn_events_release');
            $table->dropColumn('release');
        });
    }
};
