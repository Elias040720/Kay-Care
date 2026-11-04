<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->string('type', 16)->default('CRED');
            // Stored in UTC; the original UTC offset is kept to reproduce the
            // local wall-clock time and to compute the age at the encounter.
            $table->dateTime('occurred_at');
            $table->string('occurred_at_offset', 6);
            $table->text('clinical_note')->nullable();
            $table->decimal('weight_kg', 6, 3)->nullable();
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->decimal('hb_g_dl', 4, 1)->nullable();
            $table->string('status', 16)->default('draft');
            $table->foreignUuid('responsible_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'occurred_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounters');
    }
};
