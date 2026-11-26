<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Asigna las imágenes locales (/products/<SKU>.jpg) a los 24 productos demo
 * existentes que aún tienen image_url nulo, sin sobrescribir imágenes
 * cargadas o editadas desde el panel de administración.
 */
return new class extends Migration
{
    private const SKUS = [
        'KCF-VIT-001', 'KCF-VIT-002', 'KCF-VIT-003', 'KCF-VIT-004',
        'KCF-PIEL-001', 'KCF-PIEL-002', 'KCF-PIEL-003', 'KCF-PIEL-004',
        'KCF-HIG-001', 'KCF-HIG-002', 'KCF-HIG-003', 'KCF-HIG-004',
        'KCF-PA-001', 'KCF-PA-002', 'KCF-PA-003', 'KCF-PA-004',
        'KCF-GR-001', 'KCF-GR-002', 'KCF-GR-003', 'KCF-GR-004',
        'KCF-BB-001', 'KCF-BB-002', 'KCF-BB-003', 'KCF-BB-004',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            foreach (self::SKUS as $sku) {
                DB::table('products')
                    ->where('sku', $sku)
                    ->whereNull('image_path')
                    ->update([
                        'image_path' => "/products/{$sku}.jpg",
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            foreach (self::SKUS as $sku) {
                DB::table('products')
                    ->where('sku', $sku)
                    ->where('image_path', "/products/{$sku}.jpg")
                    ->update([
                        'image_path' => null,
                        'updated_at' => now(),
                    ]);
            }
        });
    }
};
