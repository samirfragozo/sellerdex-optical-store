<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCatalogSeeder extends Seeder
{
    /** @var array<string,int> */
    private array $categoryIds = [];

    private const LENS_TIER_MIN = [
        'Sin Filtro' => 125000,
        'Blue Cut' => 195000,
        'Foto Blue Cut' => 295000,
    ];

    public function run(): void
    {
        $this->seedLenses();
        $this->seedFrames();
        $this->seedSunglasses();
        $this->seedConsumables();
        $this->seedAccessories();
        $this->seedContactLenses();
        $this->seedServices();
    }

    /** Retail price for a lens: the greater of cost×4 (rounded to 1.000) or the filter floor. */
    public static function lensPrice(int $cost, string $filter): int
    {
        $markup = (int) (round($cost * 4 / 1000) * 1000);

        return max($markup, self::LENS_TIER_MIN[$filter] ?? 0);
    }

    private function categoryId(string $name): int
    {
        return $this->categoryIds[$name] ??= ProductCategory::where('name', $name)->value('id');
    }

    private function upsert(string $sku, array $attributes): void
    {
        // Title-case the display name (first letter of each word) without lowercasing
        // acronyms/codes like LC, CR-39, TR-90, X1/X3.
        if (isset($attributes['name'])) {
            $attributes['name'] = ucwords($attributes['name']);
        }

        Product::updateOrCreate(['sku' => $sku], $attributes);
    }

    private function seedFrames(): void
    {
        $structures = ['Completas', 'Semi Al Aire', 'Tres Piezas'];
        $materials = ['Pasta', 'TR-90', 'Acetato', 'Metal', 'Titanio', 'Aluminio'];
        $monturaId = $this->categoryId('Montura');

        foreach ($structures as $structure) {
            foreach ($materials as $material) {
                $sku = 'MNT-'.strtoupper(Str::slug("{$structure}-{$material}"));
                $this->upsert($sku, [
                    'product_category_id' => $monturaId,
                    'name' => "Montura {$structure} {$material}",
                    'cost' => 0,
                    'price' => 0,
                    'is_stockable' => true,
                    'stock' => 0,
                    'is_active' => true,
                    'specs' => ['structure' => $structure, 'material' => $material, 'color' => null, 'brand' => null],
                ]);
            }
        }
    }

    private function seedSunglasses(): void
    {
        $monturaId = $this->categoryId('Montura');
        // [sku, name, material, cost, price]
        $rows = [
            ['GS-PASTA', 'Gafas de sol pasta', 'Pasta', 2500, 25000],
            ['GS-ACETATO', 'Gafas de sol acetato', 'Acetato', 22000, 60000],
        ];
        foreach ($rows as [$sku, $name, $material, $cost, $price]) {
            $this->upsert($sku, [
                'product_category_id' => $monturaId,
                'name' => $name,
                'cost' => $cost,
                'price' => $price,
                'is_stockable' => true,
                'stock' => 0,
                'is_active' => true,
                'specs' => ['kind' => 'sunglasses', 'material' => $material],
            ]);
        }
    }

    private function seedConsumables(): void
    {
        $accId = $this->categoryId('Accesorio');
        // [sku, name, cost, price]
        $rows = [
            ['ACC-FORRO-SMALL', 'Forro pequeño', 2900, 10000],
            ['ACC-FORRO-LARGE', 'Forro grande', 4000, 15000],
            ['ACC-PANO', 'Paño', 600, 2000],
            ['ACC-LIQUIDO', 'Líquido de limpieza', 2000, 8000],
            ['ACC-BOLSA-PAPEL', 'Bolsa de papel', 1000, 0],
            ['ACC-BOLSA-PLASTICO', 'Bolsa de plástico', 240, 0],
            ['ACC-FUNDA', 'Funda', 500, 0],
        ];
        foreach ($rows as [$sku, $name, $cost, $price]) {
            $this->upsert($sku, [
                'product_category_id' => $accId, 'name' => $name, 'cost' => $cost, 'price' => $price,
                'is_stockable' => true, 'stock' => 0, 'is_active' => true, 'specs' => null,
            ]);
        }
    }

    private function seedAccessories(): void
    {
        $accId = $this->categoryId('Accesorio');
        $rows = [
            ['ACC-GOTAS-CARMELUB', 'Gotas Carmelub', 30000, 50000],
            ['ACC-GOTAS-FREEGEN', 'Gotas Freegen', 30000, 50000],
            ['ACC-PORTA-OREJAS', 'Porta orejas en silicona', 2500, 10000],
            ['ACC-LAGRIMAL-AIRE', 'Lagrimal de aire', 2000, 10000],
            ['ACC-SOLUCION-LC', 'Solución limpieza LC + porta lentes', 12000, 25000],
        ];
        foreach ($rows as [$sku, $name, $cost, $price]) {
            $this->upsert($sku, [
                'product_category_id' => $accId, 'name' => $name, 'cost' => $cost, 'price' => $price,
                'is_stockable' => true, 'stock' => 0, 'is_active' => true, 'specs' => null,
            ]);
        }
    }

    private function seedContactLenses(): void
    {
        $accId = $this->categoryId('Accesorio');
        // [sku, name, cost, price, includes(bool), correction]
        $rows = [
            ['ACC-LC-COSMETICOS', 'Lentes de contacto cosméticos X1 par', 55000, 75000, false, null],
            ['ACC-LC-FORM-X1', 'Lentes de contacto formulados esféricos X1 par', 28000, 115000, true, 'spheric'],
            ['ACC-LC-CONFORTVUE-X3', 'Caja LC formulados esféricos X3 — Confortvue', 78000, 225000, true, 'spheric'],
            ['ACC-LC-JJ-X3', 'Caja LC formulados esféricos X3 — Johnson & Johnson', 140000, 300000, true, 'spheric'],
            ['ACC-LC-AIROPTIX-X3', 'Caja LC formulados esféricos X3 — Air Optix', 200000, 430000, true, 'spheric'],
            ['ACC-LC-AIROPTIX-CYL-X3', 'Caja LC formulados esféricos + cilindro X3 — Air Optix', 219000, 450000, true, 'spheric_cylinder'],
        ];
        foreach ($rows as [$sku, $name, $cost, $price, $includes, $correction]) {
            $specs = ['kind' => 'contact_lens', 'correction' => $correction];
            if ($includes) {
                $specs['includes'] = ['ACC-SOLUCION-LC'];
            }
            $this->upsert($sku, [
                'product_category_id' => $accId, 'name' => $name, 'cost' => $cost, 'price' => $price,
                'is_stockable' => true, 'stock' => 0, 'is_active' => true, 'specs' => $specs,
            ]);
        }
    }

    private function seedServices(): void
    {
        $srvId = $this->categoryId('Servicio');
        // [sku, name, cost, price]
        $rows = [
            ['SRV-EXAMEN', 'Examen visual', 35000, 35000],
            ['SRV-CAMBIO-LENTES', 'Cambio de lentes', 10000, 30000],
            ['SRV-REPARACION-PATICA', 'Reparación de pática', 10000, 25000],
        ];
        foreach ($rows as [$sku, $name, $cost, $price]) {
            $this->upsert($sku, [
                'product_category_id' => $srvId, 'name' => $name, 'cost' => $cost, 'price' => $price,
                'is_stockable' => false, 'stock' => null, 'is_active' => true, 'specs' => null,
            ]);
        }
    }

    private function seedLenses(): void
    {
        // [sku, design, process, material, filter, cost]
        $rows = [
            ['ML-001', 'Monofocal', 'Terminado', 'Material 1.56', 'Sin Filtro', 6000],
            ['ML-002', 'Monofocal', 'Terminado', 'Material 1.56', 'Blue Cut', 20000],
            ['ML-003', 'Monofocal', 'Terminado', 'Material 1.56', 'Foto Blue Cut', 50000],
            ['ML-004', 'Monofocal', 'Terminado', 'Policarbonato', 'Sin Filtro', 13000],
            ['ML-005', 'Monofocal', 'Terminado', 'Policarbonato', 'Blue Cut', 50000],
            ['ML-006', 'Monofocal', 'Terminado', 'Policarbonato', 'Foto Blue Cut', 110000],
            ['ML-007', 'Monofocal', 'Rango Extendido', 'CR-39', 'Sin Filtro', 12000],
            ['ML-008', 'Monofocal', 'Rango Extendido', 'CR-39', 'Blue Cut', 50000],
            ['ML-009', 'Monofocal', 'Rango Extendido', 'Material 1.56', 'Foto Blue Cut', 80000],
            ['ML-010', 'Monofocal', 'Rango Extendido', 'Policarbonato', 'Sin Filtro', 30000],
            ['ML-011', 'Monofocal', 'Rango Extendido', 'Policarbonato', 'Blue Cut', 70000],
            ['ML-012', 'Monofocal', 'Rango Extendido', 'Material 1.61', 'Blue Cut', 70000],
            ['ML-013', 'Monofocal', 'Rango Extendido', 'Policarbonato', 'Foto Blue Cut', 130000],
            ['ML-014', 'Monofocal', 'Tallado Convencional', 'CR-39', 'Sin Filtro', 20000],
            ['ML-015', 'Monofocal', 'Tallado Convencional', 'Material 1.56', 'Blue Cut', 85000],
            ['ML-016', 'Monofocal', 'Tallado Convencional', 'Material 1.56', 'Foto Blue Cut', 110000],
            ['ML-017', 'Monofocal', 'Tallado Convencional', 'Policarbonato', 'Sin Filtro', 65000],
            ['ML-018', 'Monofocal', 'Tallado Convencional', 'Policarbonato', 'Blue Cut', 90000],
            ['ML-019', 'Monofocal', 'Tallado Convencional', 'Policarbonato', 'Foto Blue Cut', 150000],
            ['ML-020', 'Monofocal', 'Tallado Convencional', 'Material 1.61', 'Blue Cut', 120000],
            ['ML-021', 'Monofocal', 'Tallado Convencional', 'Material 1.67', 'Blue Cut', 150000],
            ['ML-022', 'Monofocal', 'Tallado Convencional', 'Material 1.67', 'Foto Blue Cut', 250000],
            ['ML-023', 'Monofocal', 'Tallado Convencional', 'Material 1.74', 'Sin Filtro', 250000],
            ['ML-024', 'Monofocal', 'Digital Plus (Freeform)', 'CR-39', 'Sin Filtro', 74000],
            ['ML-025', 'Monofocal', 'Digital Plus (Freeform)', 'Material 1.56', 'Blue Cut', 105000],
            ['ML-026', 'Monofocal', 'Digital Plus (Freeform)', 'Material 1.56', 'Foto Blue Cut', 126000],
            ['ML-027', 'Monofocal', 'Digital Plus (Freeform)', 'Policarbonato', 'Sin Filtro', 84000],
            ['ML-028', 'Monofocal', 'Digital Plus (Freeform)', 'Policarbonato', 'Blue Cut', 116000],
            ['ML-029', 'Monofocal', 'Digital Plus (Freeform)', 'Policarbonato', 'Foto Blue Cut', 174000],
            ['ML-030', 'Monofocal', 'Digital Plus (Freeform)', 'Material 1.61', 'Blue Cut', 137000],
            ['ML-031', 'Monofocal', 'Digital Plus (Freeform)', 'Material 1.67', 'Blue Cut', 195000],
            ['ML-032', 'Monofocal', 'Digital Plus (Freeform)', 'Material 1.67', 'Foto Blue Cut', 247000],
            ['ML-033', 'Monofocal', 'Digital Plus (Freeform)', 'Material 1.74', 'Blue Cut', 473000],
            ['ML-034', 'Monofocal', 'Digital Plus (Freeform)', 'Material 1.74', 'Foto Blue Cut', 578000],
            ['ML-035', 'Bifocal', 'Terminado', 'Material 1.56', 'Sin Filtro', 8000],
            ['ML-036', 'Bifocal', 'Terminado', 'Material 1.56', 'Blue Cut', 65000],
            ['ML-037', 'Bifocal', 'Terminado', 'Material 1.56', 'Foto Blue Cut', 110000],
            ['ML-038', 'Bifocal', 'Tallado Convencional', 'Material 1.56', 'Sin Filtro', 25000],
            ['ML-039', 'Bifocal', 'Tallado Convencional', 'Material 1.56', 'Blue Cut', 90000],
            ['ML-040', 'Bifocal', 'Tallado Convencional', 'Material 1.56', 'Foto Blue Cut', 125000],
            ['ML-041', 'Bifocal', 'Tallado Convencional', 'Policarbonato', 'Sin Filtro', 70000],
            ['ML-042', 'Bifocal', 'Digital', 'CR-39', 'Sin Filtro', 74000],
            ['ML-043', 'Bifocal', 'Digital', 'Material 1.56', 'Blue Cut', 105000],
            ['ML-044', 'Bifocal', 'Digital', 'Material 1.56', 'Foto Blue Cut', 126000],
            ['ML-045', 'Bifocal', 'Digital', 'Policarbonato', 'Sin Filtro', 84000],
            ['ML-046', 'Bifocal', 'Digital', 'Policarbonato', 'Blue Cut', 116000],
            ['ML-047', 'Bifocal', 'Digital', 'Policarbonato', 'Foto Blue Cut', 174000],
            ['ML-048', 'Bifocal', 'Digital', 'Material 1.61', 'Blue Cut', 137000],
            ['ML-049', 'Bifocal', 'Digital', 'Material 1.67', 'Blue Cut', 195000],
            ['ML-050', 'Bifocal', 'Digital', 'Material 1.67', 'Foto Blue Cut', 242000],
            ['ML-051', 'Bifocal', 'Digital', 'Material 1.74', 'Blue Cut', 420000],
            ['ML-052', 'Progresivo', 'Terminado', 'Material 1.56', 'Sin Filtro', 30000],
            ['ML-053', 'Progresivo', 'Terminado', 'Material 1.56', 'Blue Cut', 65000],
            ['ML-054', 'Progresivo', 'Terminado', 'Material 1.56', 'Foto Blue Cut', 100000],
            ['ML-055', 'Progresivo', 'Tallado Convencional', 'Material 1.56', 'Sin Filtro', 40000],
            ['ML-056', 'Progresivo', 'Tallado Convencional', 'Material 1.56', 'Blue Cut', 100000],
            ['ML-057', 'Progresivo', 'Tallado Convencional', 'Material 1.56', 'Foto Blue Cut', 140000],
            ['ML-058', 'Progresivo', 'Tallado Convencional', 'Policarbonato', 'Sin Filtro', 90000],
            ['ML-059', 'Progresivo', 'Digital', 'CR-39', 'Sin Filtro', 83000],
            ['ML-060', 'Progresivo', 'Digital', 'Material 1.56', 'Blue Cut', 125000],
            ['ML-061', 'Progresivo', 'Digital', 'Material 1.56', 'Foto Blue Cut', 154000],
            ['ML-062', 'Progresivo', 'Digital', 'Policarbonato', 'Sin Filtro', 104000],
            ['ML-063', 'Progresivo', 'Digital', 'Policarbonato', 'Blue Cut', 143000],
            ['ML-064', 'Progresivo', 'Digital', 'Policarbonato', 'Foto Blue Cut', 201000],
            ['ML-065', 'Progresivo', 'Digital', 'Material 1.61', 'Blue Cut', 147000],
            ['ML-066', 'Progresivo', 'Digital', 'Material 1.67', 'Blue Cut', 221000],
            ['ML-067', 'Progresivo', 'Digital', 'Material 1.67', 'Foto Blue Cut', 273000],
            ['ML-068', 'Progresivo', 'Digital', 'Material 1.74', 'Blue Cut', 441000],
            ['ML-069', 'Progresivo', 'Digital', 'Material 1.74', 'Foto Blue Cut', 557000],
        ];

        $lenteId = $this->categoryId('Lente');

        foreach ($rows as [$sku, $design, $process, $material, $filter, $cost]) {
            $this->upsert($sku, [
                'product_category_id' => $lenteId,
                'name' => trim("{$design} {$process} {$material} {$filter}"),
                'cost' => $cost,
                'price' => self::lensPrice($cost, $filter),
                'is_stockable' => false,
                'stock' => null,
                'is_active' => true,
                'specs' => compact('design', 'process', 'material', 'filter'),
            ]);
        }
    }
}
