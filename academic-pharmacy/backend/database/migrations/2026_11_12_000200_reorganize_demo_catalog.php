<?php

use Database\Seeders\CatalogSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migración de datos (una sola vez) para bases ya existentes:
 * - renombra las categorías anteriores a las seis categorías de la tienda,
 * - mueve tres productos ficticios a su nueva categoría,
 * - asigna las promociones ficticias a los productos que no tienen una.
 *
 * Solo toca filas que siguen como las dejó la versión anterior del seeder,
 * de modo que no pisa cambios hechos desde la administración. En una base
 * vacía no hace nada (el CatalogSeeder ya crea la estructura nueva).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            foreach (CatalogSeeder::CATEGORIES as $i => [$name, $slug, $description, $legacySlug]) {
                $exists = DB::table('categories')->where('slug', $slug)->exists();
                $query = DB::table('categories')->where('slug', $exists ? $slug : $legacySlug);
                $query->update([
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $description,
                    'sort_order' => $i + 1,
                    'updated_at' => now(),
                ]);
            }

            $categoryIds = DB::table('categories')->pluck('id', 'slug');
            foreach (CatalogSeeder::MOVES as $sku => [$fromLegacy, $to]) {
                $fromSlug = collect(CatalogSeeder::CATEGORIES)->firstWhere(3, $fromLegacy)[1] ?? null;
                if (! isset($categoryIds[$fromSlug], $categoryIds[$to])) {
                    continue;
                }
                DB::table('products')
                    ->where('sku', $sku)
                    ->where('category_id', $categoryIds[$fromSlug])
                    ->update(['category_id' => $categoryIds[$to], 'updated_at' => now()]);
            }

            foreach (CatalogSeeder::PROMOTIONS as $sku => $compareAt) {
                $cents = (int) round($compareAt * 100);
                DB::table('products')
                    ->where('sku', $sku)
                    ->whereNull('compare_at_price_cents')
                    ->where('price_cents', '<', $cents)
                    ->update(['compare_at_price_cents' => $cents, 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        // Migración de datos: no se revierte automáticamente (los datos son
        // ficticios y la versión anterior sigue funcionando con estos nombres).
    }
};
