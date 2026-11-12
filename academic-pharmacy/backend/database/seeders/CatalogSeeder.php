<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Catálogo 100 % ficticio: marcas inventadas y solo productos de venta
 * libre / cuidado personal. Ningún texto constituye consejo médico.
 */
class CatalogSeeder extends Seeder
{
    private const DISCLAIMER = ' Producto ficticio con fines académicos: no constituye recomendación médica ni oferta comercial.';

    /**
     * Categorías de la tienda: [nombre, slug, descripción, slug anterior].
     * El slug anterior permite que la migración de datos 2026_11_12_000200
     * renombre una base ya existente sin duplicar categorías.
     */
    public const CATEGORIES = [
        ['Medicamentos de venta libre', 'medicamentos-venta-libre', 'Productos de venta libre de uso común. Lee siempre el rotulado.', 'garganta-y-resfrio'],
        ['Vitaminas y bienestar', 'vitaminas-y-bienestar', 'Vitaminas, complementos e infusiones para el día a día.', 'bienestar-diario'],
        ['Cuidado personal', 'cuidado-personal', 'Protección solar, hidratación y cuidado de la piel.', 'cuidado-de-la-piel'],
        ['Higiene', 'higiene', 'Higiene oral, de manos y del hogar.', 'higiene-personal'],
        ['Primeros auxilios', 'primeros-auxilios', 'Botiquín básico para el hogar.', 'primeros-auxilios'],
        ['Cuidado infantil', 'cuidado-infantil', 'Cuidado suave para bebés y niños.', 'bebe-y-familia'],
    ];

    /** Productos que cambian de categoría respecto a la versión anterior: sku => [slug anterior, slug nuevo]. */
    public const MOVES = [
        'KCF-GR-002' => ['garganta-y-resfrio', 'vitaminas-y-bienestar'],
        'KCF-GR-004' => ['garganta-y-resfrio', 'higiene'],
        'KCF-BB-003' => ['bebe-y-familia', 'medicamentos-venta-libre'],
    ];

    /** Promociones ficticias: sku => precio «antes» en S/. */
    public const PROMOTIONS = [
        'KCF-PIEL-001' => 58.90,
        'KCF-VIT-001' => 29.90,
        'KCF-VIT-002' => 46.90,
        'KCF-HIG-002' => 8.90,
        'KCF-PA-003' => 34.90,
        'KCF-BB-002' => 12.90,
    ];

    public function run(): void
    {
        $categories = [
            ...self::CATEGORIES,
        ];

        $categoryIds = [];
        foreach ($categories as $i => [$name, $slug, $description]) {
            $categoryIds[$slug] = Category::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description, 'sort_order' => $i + 1],
            )->id;
        }

        // [categoría, sku, nombre, marca, presentación, precio S/, stock, ilustración, destacado, resumen]
        $products = [
            ['vitaminas-y-bienestar', 'KCF-VIT-001', 'Vitamina C 500 mg masticable', 'Verdal', 'Frasco x 60 tabletas', 24.90, 48, 'bottle', true, 'Tabletas masticables sabor naranja.'],
            ['vitaminas-y-bienestar', 'KCF-VIT-002', 'Multivitamínico Adulto Activo', 'Nativa Lab', 'Caja x 30 cápsulas', 39.50, 22, 'capsule', true, 'Complejo de vitaminas y minerales para el día a día.'],
            ['vitaminas-y-bienestar', 'KCF-VIT-003', 'Magnesio + Zinc efervescente', 'Lumea', 'Tubo x 20 tabletas', 18.90, 4, 'tube', false, 'Tabletas efervescentes sabor limón.'],
            ['vitaminas-y-bienestar', 'KCF-VIT-004', 'Omega 3 de origen marino', 'Nativa Lab', 'Frasco x 90 perlas', 54.00, 15, 'capsule', false, 'Perlas blandas de aceite de pescado.'],
            ['cuidado-personal', 'KCF-PIEL-001', 'Protector solar FPS 50+ toque seco', 'Solárea', 'Tubo x 60 ml', 49.90, 31, 'tube', true, 'Textura ligera, resistente al agua.'],
            ['cuidado-personal', 'KCF-PIEL-002', 'Gel de aloe vera calmante', 'Verdal', 'Frasco x 200 ml', 16.50, 40, 'jar', false, 'Gel refrescante para después del sol.'],
            ['cuidado-personal', 'KCF-PIEL-003', 'Crema hidratante de urea 10 %', 'Dermavia', 'Pote x 120 g', 32.00, 0, 'jar', false, 'Para piel seca de manos y pies.'],
            ['cuidado-personal', 'KCF-PIEL-004', 'Bálsamo labial con FPS 15', 'Solárea', 'Barra x 4,5 g', 9.90, 65, 'tube', false, 'Hidratación y protección para labios.'],
            ['higiene', 'KCF-HIG-001', 'Alcohol en gel 70 %', 'Pura Mano', 'Frasco x 250 ml', 8.90, 80, 'bottle', false, 'Higienizante de manos sin enjuague.'],
            ['higiene', 'KCF-HIG-002', 'Pasta dental con flúor menta suave', 'Brisa Dental', 'Tubo x 100 ml', 7.50, 55, 'tube', false, 'Protección anticaries de uso diario.'],
            ['higiene', 'KCF-HIG-003', 'Enjuague bucal sin alcohol', 'Brisa Dental', 'Frasco x 500 ml', 19.90, 3, 'bottle', true, 'Aliento fresco, sin ardor.'],
            ['higiene', 'KCF-HIG-004', 'Hilo dental encerado', 'Brisa Dental', 'Rollo x 50 m', 6.90, 70, 'box', false, 'Hilo con cera de menta.'],
            ['primeros-auxilios', 'KCF-PA-001', 'Curitas transparentes surtidas', 'Botiquín+', 'Caja x 40 unidades', 6.50, 90, 'box', false, 'Apósitos adhesivos de varios tamaños.'],
            ['primeros-auxilios', 'KCF-PA-002', 'Gasas estériles 10 x 10 cm', 'Botiquín+', 'Sobre x 10 unidades', 5.90, 60, 'sachet', false, 'Gasa de algodón, uso externo.'],
            ['primeros-auxilios', 'KCF-PA-003', 'Termómetro digital flexible', 'Medix Home', 'Unidad', 29.90, 12, 'box', true, 'Lectura en 60 segundos, punta flexible.'],
            ['primeros-auxilios', 'KCF-PA-004', 'Venda elástica 10 cm', 'Botiquín+', 'Rollo x 5 m', 7.90, 2, 'box', false, 'Venda de algodón y elastano.'],
            ['medicamentos-venta-libre', 'KCF-GR-001', 'Pastillas de miel y eucalipto', 'Lumea', 'Lata x 24 pastillas', 11.90, 44, 'jar', true, 'Pastillas para chupar, sabor miel.'],
            ['vitaminas-y-bienestar', 'KCF-GR-002', 'Infusión de jengibre y limón', 'Verdal', 'Caja x 20 sobres', 9.50, 36, 'sachet', false, 'Bebida caliente reconfortante.'],
            ['medicamentos-venta-libre', 'KCF-GR-003', 'Solución salina nasal', 'Medix Home', 'Spray x 30 ml', 14.90, 27, 'spray', false, 'Agua de mar isotónica para higiene nasal.'],
            ['higiene', 'KCF-GR-004', 'Pañuelos con bálsamo', 'Suavia', 'Pack x 6 paquetes', 8.50, 0, 'box', false, 'Pañuelos suaves con aloe.'],
            ['cuidado-infantil', 'KCF-BB-001', 'Crema protectora para pañal', 'Suavia Baby', 'Tubo x 90 g', 21.90, 25, 'tube', false, 'Con óxido de zinc y caléndula.'],
            ['cuidado-infantil', 'KCF-BB-002', 'Toallitas húmedas sin fragancia', 'Suavia Baby', 'Paquete x 80 unidades', 10.90, 58, 'box', true, 'Toallitas hipoalergénicas.'],
            ['medicamentos-venta-libre', 'KCF-BB-003', 'Sales de rehidratación oral sabor fresa', 'Medix Home', 'Caja x 4 sobres', 12.50, 18, 'sachet', false, 'Para preparar en agua hervida fría.'],
            ['cuidado-infantil', 'KCF-BB-004', 'Champú suave para bebé', 'Suavia Baby', 'Frasco x 400 ml', 17.90, 9, 'bottle', false, 'Fórmula sin lágrimas.'],
        ];

        foreach ($products as [$category, $sku, $name, $brand, $presentation, $price, $stock, $illustration, $featured, $summary]) {
            // firstOrCreate: re-ejecutar el seeder (p. ej. en cada arranque del contenedor)
            // nunca sobrescribe stock ni cambios hechos desde la administración.
            $product = Product::query()->firstOrCreate(
                ['sku' => $sku],
                [
                    'category_id' => $categoryIds[$category],
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'brand' => $brand,
                    'presentation' => $presentation,
                    'short_description' => $summary,
                    'description' => $summary.' Marca y producto inventados para la tienda demostrativa Kay Care Farmacia.'.self::DISCLAIMER,
                    'price_cents' => (int) round($price * 100),
                    'compare_at_price_cents' => isset(self::PROMOTIONS[$sku]) ? (int) round(self::PROMOTIONS[$sku] * 100) : null,
                    'stock' => $stock,
                    'low_stock_threshold' => 5,
                    'illustration' => $illustration,
                    'is_active' => true,
                    'is_featured' => $featured,
                ],
            );

            if ($product->wasRecentlyCreated && $stock > 0) {
                StockMovement::query()->create([
                    'product_id' => $product->id,
                    'delta' => $stock,
                    'stock_after' => $stock,
                    'reason' => StockMovement::REASON_SEED,
                    'note' => 'Carga inicial de datos demo',
                ]);
            }
        }
    }
}
