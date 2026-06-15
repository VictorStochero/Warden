<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->create('wdn_api_tokens', function (Blueprint $table): void {
            // Read-only API tokens (§5.7) for automation / external dashboards.
            // Only the SHA-256 hash is stored; the plaintext is shown once at mint.
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection()->dropIfExists('wdn_api_tokens');
    }
};
