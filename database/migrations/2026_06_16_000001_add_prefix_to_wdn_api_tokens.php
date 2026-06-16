<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->table('wdn_api_tokens', function (Blueprint $table): void {
            // §9.5: an indexable plaintext prefix. findByPlaintext() narrows on
            // this prefix and then compares the full hash with hash_equals(),
            // closing the timing channel that a bare WHERE token = ? opens.
            $table->string('prefix', 12)->nullable()->index()->after('name');
        });
    }

    public function down(): void
    {
        Schema::connection()->table('wdn_api_tokens', function (Blueprint $table): void {
            $table->dropIndex(['prefix']);
            $table->dropColumn('prefix');
        });
    }
};
