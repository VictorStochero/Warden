<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->table('wdn_projects', function (Blueprint $table) {
            // Optional grouping + light CRM metadata, editable from the dashboard.
            $table->unsignedBigInteger('group_id')->nullable()->after('slug');
            $table->string('client')->nullable()->after('group_id');
            $table->string('contact')->nullable()->after('client');
            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::connection()->table('wdn_projects', function (Blueprint $table) {
            $table->dropIndex(['group_id']);
            $table->dropColumn(['group_id', 'client', 'contact']);
        });
    }
};
