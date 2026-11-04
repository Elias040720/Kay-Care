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

    public function run(): void
    {
        $categories = [
            ['Bienestar diario', 'bienestar-diario', 'Vitaminas y complementos de uso cotidiano.'],
            ['Cuidado de la piel', 'cuidado-de-la-piel', 'Hidratación, protección solar y cuidado dermocosmético.'],
            ['Higiene y cuidado personal', 'higiene-personal', 'Higiene oral, manos y cuidado corporal.'],
            ['Primeros auxilios', 'primeros-auxilios', 'Botiquín básico para el hogar.'],
            ['Garganta y resfrío', 'garganta-y-resfrio', 'Alivio sintomático de venta libre y bebidas calientes.'],
            ['Bebé y familia', 'bebe-y-familia', 'Cuidado suave para toda la familia.'],
        ];

        $categoryIds = [];
        foreach ($categories as $i => [$name, $slug, $description]) {
            $categoryIds[$slug] = Category::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description, 'sort_order' => $i + 1],
            )->id;
        }

        // [categoría, sku, nombre, marca, presentación, precio S/, stock, ilustración, destacado, resumen]
        $products = [
            ['bienestar-diario', 'KCF-VIT-001', 'Vitamina C 500 mg masticable', 'Verdal', 'Frasco x 60 tabletas', 24.90, 48, 'bottle', true, 'Tabletas masticables sabor naranja.'],
            ['bienestar-diario', 'KCF-VIT-002', 'Multivitamínico Adulto Activo', 'Nativa Lab', 'Caja x 30 cápsulas', 39.50, 22, 'capsule', true, 'Complejo de vitaminas y minerales para el día a día.'],
            ['bienestar-diario', 'KCF-VIT-003', 'Magnesio + Zinc efervescente', 'Lumea', 'Tubo x 20 tabletas', 18.90, 4, 'tube', false, 'Tabletas efervescentes sabor limón.'],
            ['bienestar-diario', 'KCF-VIT-004', 'Omega 3 de origen marino', 'Nativa Lab', 'Frasco x 90 perlas', 54.00, 15, 'capsule', false, 'Perlas blandas de aceite de pescado.'],
            ['cuidado-de-la-piel', 'KCF-PIEL-001', 'Protector solar FPS 50+ toque seco', 'Solárea', 'Tubo x 60 ml', 49.90, 31, 'tube', true, 'Textura ligera, resistente al agua.'],
            ['cuidado-de-la-piel', 'KCF-PIEL-002', 'Gel de aloe vera calmante', 'Verdal', 'Frasco x 200 ml', 16.50, 40, 'jar', false, 'Gel refrescante para después del sol.'],
            ['cuidado-de-la-piel', 'KCF-PIEL-003', 'Crema hidratante de urea 10 %', 'Dermavia', 'Pote x 120 g', 32.00, 0, 'jar', false, 'Para piel seca de manos y pies.'],
            ['cuidado-de-la-piel', 'KCF-PIEL-004', 'Bálsamo labial con FPS 15', 'Solárea', 'Barra x 4,5 g', 9.90, 65, 'tube', false, 'Hidratación y protección para labios.'],
            ['higiene-personal', 'KCF-HIG-001', 'Alcohol en gel 70 %', 'Pura Mano', 'Frasco x 250 ml', 8.90, 80, 'bottle', false, 'Higienizante de manos sin enjuague.'],
            ['higiene-personal', 'KCF-HIG-002', 'Pasta dental con flúor menta suave', 'Brisa Dental', 'Tubo x 100 ml', 7.50, 55, 'tube', false, 'Protección anticaries de uso diario.'],
            ['higiene-personal', 'KCF-HIG-003', 'Enjuague bucal sin alcohol', 'Brisa Dental', 'Frasco x 500 ml', 19.90, 3, 'bottle', true, 'Aliento fresco, sin ardor.'],
            ['higiene-personal', 'KCF-HIG-004', 'Hilo dental encerado', 'Brisa Dental', 'Rollo x 50 m', 6.90, 70, 'box', false, 'Hilo con cera de menta.'],
            ['primeros-auxilios', 'KCF-PA-001', 'Curitas transparentes surtidas', 'Botiquín+', 'Caja x 40 unidades', 6.50, 90, 'box', false, 'Apósitos adhesivos de varios tamaños.'],
            ['primeros-auxilios', 'KCF-PA-002', 'Gasas estériles 10 x 10 cm', 'Botiquín+', 'Sobre x 10 unidades', 5.90, 60, 'sachet', false, 'Gasa de algodón, uso externo.'],
            ['primeros-auxilios', 'KCF-PA-003', 'Termómetro digital flexible', 'Medix Home', 'Unidad', 29.90, 12, 'box', true, 'Lectura en 60 segundos, punta flexible.'],
            ['primeros-auxilios', 'KCF-PA-004', 'Venda elástica 10 cm', 'Botiquín+', 'Rollo x 5 m', 7.90, 2, 'box', false, 'Venda de algodón y elastano.'],
            ['garganta-y-resfrio', 'KCF-GR-001', 'Pastillas de miel y eucalipto', 'Lumea', 'Lata x 24 pastillas', 11.90, 44, 'jar', true, 'Pastillas para chupar, sabor miel.'],
            ['garganta-y-resfrio', 'KCF-GR-002', 'Infusión de jengibre y limón', 'Verdal', 'Caja x 20 sobres', 9.50, 36, 'sachet', false, 'Bebida caliente reconfortante.'],
            ['garganta-y-resfrio', 'KCF-GR-003', 'Solución salina nasal', 'Medix Home', 'Spray x 30 ml', 14.90, 27, 'spray', false, 'Agua de mar isotónica para higiene nasal.'],
            ['garganta-y-resfrio', 'KCF-GR-004', 'Pañuelos con bálsamo', 'Suavia', 'Pack x 6 paquetes', 8.50, 0, 'box', false, 'Pañuelos suaves con aloe.'],
            ['bebe-y-familia', 'KCF-BB-001', 'Crema protectora para pañal', 'Suavia Baby', 'Tubo x 90 g', 21.90, 25, 'tube', false, 'Con óxido de zinc y caléndula.'],
            ['bebe-y-familia', 'KCF-BB-002', 'Toallitas húmedas sin fragancia', 'Suavia Baby', 'Paquete x 80 unidades', 10.90, 58, 'box', true, 'Toallitas hipoalergénicas.'],
            ['bebe-y-familia', 'KCF-BB-003', 'Sales de rehidratación oral sabor fresa', 'Medix Home', 'Caja x 4 sobres', 12.50, 18, 'sachet', false, 'Para preparar en agua hervida fría.'],
            ['bebe-y-familia', 'KCF-BB-004', 'Champú suave para bebé', 'Suavia Baby', 'Frasco x 400 ml', 17.90, 9, 'bottle', false, 'Fórmula sin lágrimas.'],
        ];

        foreach ($products as [$category, $sku, $name, $brand, $presentation, $price, $stock, $illustration, $featured, $summary]) {
            $product = Product::query()->updateOrCreate(
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
