<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Parent-global key/value settings that aren't per-project and don't
        // belong on a project's pushed config (e.g. the new-version notice
        // toggle + its cached check result). JSON value so it can hold scalars
        // or small structures. Read/written only on the parent.
        Schema::connection()->create('wdn_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection()->dropIfExists('wdn_settings');
    }
};
