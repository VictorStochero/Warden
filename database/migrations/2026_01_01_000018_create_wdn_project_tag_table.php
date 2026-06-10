<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->create('wdn_project_tag', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('tag_id');
            $table->primary(['project_id', 'tag_id']);
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::connection()->dropIfExists('wdn_project_tag');
    }
};
