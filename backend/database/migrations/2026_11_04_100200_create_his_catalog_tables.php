<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A catalog version groups items and rules with provenance and validity.
        Schema::create('his_catalog_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->string('source', 40);                  // e.g. "demo"
            $table->string('source_reference', 255)->nullable();
            $table->string('verification_status', 16)->default('draft');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'valid_from', 'valid_to']);
        });

        Schema::create('his_catalog_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('catalog_version_id')->constrained('his_catalog_versions')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('label', 200);
            $table->string('category', 30);                 // control, evaluation, lab, counseling, screening
            $table->json('allowed_diagnosis_types');        // subset of ["P","D","R"]
            $table->boolean('diagnosis_type_required')->default(false);
            $table->string('lab_mode', 20)->default('none'); // none|control_number|numeric|enum|free
            $table->json('lab_options')->nullable();        // {min,max,values}
            $table->string('verification_status', 16)->default('draft');
            $table->string('source', 40);
            $table->string('source_reference', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['catalog_version_id', 'code']);
            $table->index('code');
        });

        // Verifiable, data-driven suggestion rules (no logic in controllers).
        Schema::create('his_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('catalog_version_id')->constrained('his_catalog_versions')->restrictOnDelete();
            $table->foreignUuid('catalog_item_id')->constrained('his_catalog_items')->restrictOnDelete();
            $table->string('rule_code', 40);
            $table->string('description', 255);
            // Inclusive bounds; a NULL bound is not evaluated.
            $table->unsignedInteger('min_age_days')->nullable();
            $table->unsignedInteger('max_age_days')->nullable();
            $table->unsignedInteger('min_age_months')->nullable();
            $table->unsignedInteger('max_age_months')->nullable();
            $table->unsignedInteger('min_age_years')->nullable();
            $table->unsignedInteger('max_age_years')->nullable();
            $table->char('sex', 1)->nullable();
            $table->string('condition', 40)->nullable();    // e.g. has_anthropometry, has_hb
            $table->char('diagnosis_type_suggested', 1)->nullable();
            $table->string('lab_strategy', 30)->default('none'); // none|control_number|measurement_hb|fixed
            $table->string('lab_fixed', 10)->nullable();
            $table->unsignedSmallInteger('max_sequence')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->string('verification_status', 16)->default('draft');
            $table->string('source', 40);
            $table->string('source_reference', 255)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['catalog_version_id', 'rule_code']);
        });

        Schema::create('his_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('encounter_id')->constrained('encounters')->restrictOnDelete();
            $table->foreignUuid('catalog_item_id')->constrained('his_catalog_items')->restrictOnDelete();
            $table->char('diagnosis_type', 1)->nullable();
            $table->string('lab', 10)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->foreignUuid('confirmed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at');
            $table->timestamps();

            $table->unique(['encounter_id', 'catalog_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('his_lines');
        Schema::dropIfExists('his_rules');
        Schema::dropIfExists('his_catalog_items');
        Schema::dropIfExists('his_catalog_versions');
    }
};
