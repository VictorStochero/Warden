<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->create('wdn_dead_letter', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id');
            $table->string('batch_id', 64)->nullable();
            $table->string('reason', 255)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('reported_at');
            $table->index(['project_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::connection()->dropIfExists('wdn_dead_letter');
    }
};
