<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Event trail: who did what and when. Never stores clinical free text.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 60);
            $table->string('auditable_type', 60)->nullable();
            $table->uuid('auditable_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('event');
        });

        // Append-only snapshots of the previous state of clinical records, so
        // an edit never overwrites information without traceability.
        Schema::create('record_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('revisionable_type', 60);
            $table->uuid('revisionable_id');
            $table->unsignedInteger('revision');
            $table->json('snapshot');
            $table->json('changed_fields');
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['revisionable_type', 'revisionable_id', 'revision'], 'record_revisions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_revisions');
        Schema::dropIfExists('audit_logs');
    }
};
