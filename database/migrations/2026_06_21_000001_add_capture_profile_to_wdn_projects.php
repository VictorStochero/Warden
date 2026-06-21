<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->table('wdn_projects', function (Blueprint $table) {
            // Capture posture marker (parent-side metadata only — never pushed to
            // the child). null = undecided/legacy (shows the lean opt-in notice);
            // 'lean' = reduced profile active; 'full' = operator chose full / the
            // notice was dismissed; 'custom' = behaviour edited by hand. Existing
            // rows stay null deliberately, so an upgrade offers the opt-in.
            $table->string('capture_profile')->nullable()->after('config_version');
        });
    }

    public function down(): void
    {
        Schema::connection()->table('wdn_projects', function (Blueprint $table) {
            $table->dropColumn('capture_profile');
        });
    }
};
