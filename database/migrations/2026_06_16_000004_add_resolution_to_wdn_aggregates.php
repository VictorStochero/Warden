<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Multi-resolution rollups (§5.8): the same (project, type, key, bucket)
        // can now exist at several resolutions (e.g. 60s and 86400s), so the
        // resolution becomes part of the identity. Existing rows default to the
        // base 60s resolution.
        Schema::connection()->table('wdn_aggregates', function (Blueprint $table): void {
            $table->unsignedInteger('resolution')->default(60)->after('bucket');
        });

        Schema::connection()->table('wdn_aggregates', function (Blueprint $table): void {
            $table->dropUnique('wdn_aggregates_unique');
            $table->unique(['project_id', 'type', 'resolution', 'bucket', 'key'], 'wdn_aggregates_unique');
            $table->index(['project_id', 'type', 'resolution', 'bucket'], 'wdn_aggregates_res_lookup');
        });
    }

    public function down(): void
    {
        Schema::connection()->table('wdn_aggregates', function (Blueprint $table): void {
            $table->dropIndex('wdn_aggregates_res_lookup');
            $table->dropUnique('wdn_aggregates_unique');
            $table->unique(['project_id', 'type', 'bucket', 'key'], 'wdn_aggregates_unique');
            $table->dropColumn('resolution');
        });
    }
};
