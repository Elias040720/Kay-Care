<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Facility chart number ("número de historia clínica").
            $table->string('chart_number', 30)->unique();
            // Optional: a newborn may not have a DNI yet.
            $table->string('document_type', 12)->nullable();
            $table->string('document_number', 20)->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 150);
            $table->date('birth_date');
            $table->char('sex', 1);
            $table->string('locality', 150)->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // NULL pairs are allowed many times (MySQL 8 and SQLite semantics).
            $table->unique(['document_type', 'document_number'], 'patients_document_unique');
            $table->index('document_number');
            $table->index(['last_name', 'first_name']);
            $table->index('first_name');
            $table->index('birth_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
