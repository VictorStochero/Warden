<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->create('wdn_command_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('command');
            $table->string('status', 16)->default('queued'); // queued|running|ok|failed
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
            $table->index(['command', 'id']);
        });
    }

    public function down(): void
    {
        Schema::connection()->dropIfExists('wdn_command_runs');
    }
};
