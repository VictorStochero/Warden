<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use VictorStochero\Warden\Support\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection()->create('wdn_issue_comments', function (Blueprint $table): void {
            // Issue triage thread (§5.3): operator notes on a grouped issue. The
            // author is the dashboard operator's identity (host e-mail or label).
            $table->bigIncrements('id');
            $table->unsignedBigInteger('issue_id')->index();
            $table->string('author');
            $table->text('body');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection()->dropIfExists('wdn_issue_comments');
    }
};
