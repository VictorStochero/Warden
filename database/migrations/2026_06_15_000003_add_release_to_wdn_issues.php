<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->table('wdn_issues', function (Blueprint $table): void {
            // Deploy-aware regression (§5.6): the release the issue was last seen
            // on, and the one it was resolved on — so a recurrence on a *newer*
            // release reopens it (a real regression) while a recurrence on the
            // same deploy the operator already triaged stays resolved.
            $table->string('last_release', 64)->nullable()->after('assignee');
            $table->string('resolved_release', 64)->nullable()->after('last_release');
        });
    }

    public function down(): void
    {
        Schema::connection()->table('wdn_issues', function (Blueprint $table): void {
            $table->dropColumn(['last_release', 'resolved_release']);
        });
    }
};
