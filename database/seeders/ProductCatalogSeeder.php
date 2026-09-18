<?php

namespace Database\Seeders;

use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCatalogSeeder extends Seeder
{
    /** Null outside a company-scoped run (dev/test fixtures, unscoped like the rest of that data). */
    private ?int $companyId = null;

    /** @var array<string,int> */
    private array $categoryIds = [];

    /** Entry point for `php artisan db:seed` (dev/local): unscoped, matches the rest of the dev fixtures. */
    public function run(): void
    {
        $this->seed();
    }

    /** Entry point for provisioning a real company's catalog (see SeedCompanyDefaults). */
    public function handle(int $companyId): void
    {
        $this->companyId = $companyId;
        $this->seed();
    }

    private function seed(): void
    {
        $this->categoryIds = [];

        $this->seedFrames();
        $this->seedSunglasses();
        $this->seedConsumables();
        $this->seedAccessories();
        $this->seedContactLenses();
        $this->seedServices();
    }

    /** Map the human label used in this seeder to the stable category key. */
    private const CATEGORY_KEY = [
        'Lente' => 'lens',
        'Montura' => 'frame',
        'Accesorio' => 'accessory',
        'Servicio' => 'service',
    ];

    /** Adds company_id to a lookup key when scoped, leaving unscoped (dev/test) lookups untouched. */
    private function scopedKey(array $key): array
    {
        return $this->companyId !== null ? ['company_id' => $this->companyId, ...$key] : $key;
    }

    private function categoryId(string $name): int
    {
        $key = self::CATEGORY_KEY[$name] ?? $name;

        return $this->categoryIds[$key] ??= ProductCategory::where($this->scopedKey(['key' => $key]))->value('id');
    }

    private function upsert(string $sku, array $attributes): Product
    {
        // Title-case the display name (first letter of each word) without lowercasing
        // acronyms/codes like LC, CR-39, TR-90, X1/X3.
        if (isset($attributes['name'])) {
            $attributes['name'] = ucwords($attributes['name']);
        }

        return Product::updateOrCreate($this->scopedKey(['sku' => $sku]), $attributes);
    }

    private function seedFrames(): void
    {
        $monturaId = $this->categoryId('Montura');

        $base = $this->upsert('MNT-BASE', [
            'product_category_id' => $monturaId,
            'name' => 'Montura',
            'cost' => 0,
            'price' => 0,
            'is_stockable' => false,
            'stock' => null,
            'is_active' => true,
            'is_pos_selectable' => true,
            'specs' => null,
        ]);

        $structures = ['Completa', 'Semi Al Aire', 'Tres Piezas'];
        $materials = ['Pasta', 'TR-90', 'Acetato', 'Metal', 'Titanio', 'Aluminio'];

        $structureGroup = OptionGroup::firstOrCreate($this->scopedKey(['name' => 'Estructura']), ['is_required' => true, 'is_active' => true]);
        foreach ($structures as $i => $structure) {
            $this->upsertOption($structureGroup, $structure, 0, 0, $i + 1);
        }

        $materialGroup = OptionGroup::firstOrCreate($this->scopedKey(['name' => 'Material de Montura']), ['is_required' => true, 'is_active' => true]);
        foreach ($materials as $i => $material) {
            $this->upsertOption($materialGroup, $material, 0, 0, $i + 1);
        }

        $base->optionGroups()->syncWithPivotValues([$structureGroup->id, $materialGroup->id], []);

        foreach ($structures as $structure) {
            foreach ($materials as $material) {
                $sku = 'MNT-'.strtoupper(Str::slug("{$structure}-{$material}"));
                $variant = $this->upsert($sku, [
                    'product_category_id' => $monturaId,
                    'name' => "Montura {$structure} {$material}",
                    'cost' => 0,
                    'price' => 0,
                    'is_stockable' => true,
                    'stock' => 0,
                    'is_active' => true,
                    'is_pos_selectable' => false,
                    'base_product_id' => $base->id,
                    'specs' => ['structure' => $structure, 'material' => $material, 'color' => null, 'brand' => null],
                ]);

                $structureOptionId = Option::where('option_group_id', $structureGroup->id)->where('name', $structure)->value('id');
                $materialOptionId = Option::where('option_group_id', $materialGroup->id)->where('name', $material)->value('id');

                $variant->variantOptions()->syncWithoutDetaching([$structureOptionId, $materialOptionId]);
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
            ['ACC-ESTUCHE-SMALL', 'Estuche pequeño', 2900, 10000],
            ['ACC-ESTUCHE-LARGE', 'Estuche grande', 4000, 15000],
            ['ACC-PANO', 'Paño', 600, 2000],
            ['ACC-LIQUIDO', 'Líquido de limpieza', 2000, 8000],
            ['ACC-BOLSA-PAPEL', 'Bolsa de papel', 1000, 0],
            ['ACC-BOLSA-PLASTICO', 'Bolsa de plástico', 240, 0],
            ['ACC-FUNDA', 'Funda', 1000, 3000],
        ];
        // Auto-included by combos and never sold on their own → hidden from the POS picker.
        // Funda is also given away free inside combos (see RegisterSale::applyBag), but stays
        // sellable on its own, so it's excluded from this list.
        $nonSellable = ['ACC-BOLSA-PAPEL', 'ACC-BOLSA-PLASTICO', 'ACC-PANO'];

        foreach ($rows as [$sku, $name, $cost, $price]) {
            $this->upsert($sku, [
                'product_category_id' => $accId, 'name' => $name, 'cost' => $cost, 'price' => $price,
                'is_stockable' => true, 'stock' => 0, 'is_active' => true,
                'is_pos_selectable' => ! in_array($sku, $nonSellable, true), 'specs' => null,
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
        // [sku, name, cost, price, bundlesSolution(bool), correction]
        $rows = [
            ['ACC-LC-COSMETICOS', 'Lentes de contacto cosméticos X1 par', 55000, 75000, false, null],
            ['ACC-LC-FORM-X1', 'Lentes de contacto formulados esféricos X1 par', 28000, 115000, true, 'spheric'],
            ['ACC-LC-CONFORTVUE-X3', 'Caja LC formulados esféricos X3 — Confortvue', 78000, 225000, true, 'spheric'],
            ['ACC-LC-JJ-X3', 'Caja LC formulados esféricos X3 — Johnson & Johnson', 140000, 300000, true, 'spheric'],
            ['ACC-LC-AIROPTIX-X3', 'Caja LC formulados esféricos X3 — Air Optix', 200000, 430000, true, 'spheric'],
            ['ACC-LC-AIROPTIX-CYL-X3', 'Caja LC formulados esféricos + cilindro X3 — Air Optix', 219000, 450000, true, 'spheric_cylinder'],
        ];

        $solution = Product::where($this->scopedKey(['sku' => 'ACC-SOLUCION-LC']))->first();

        foreach ($rows as [$sku, $name, $cost, $price, $bundlesSolution, $correction]) {
            $specs = ['kind' => 'contact_lens', 'correction' => $correction];
            $product = $this->upsert($sku, [
                'product_category_id' => $accId, 'name' => $name, 'cost' => $cost, 'price' => $price,
                'is_stockable' => true, 'stock' => 0, 'is_active' => true, 'specs' => $specs,
            ]);

            if ($bundlesSolution && $solution !== null) {
                $product->additions()->syncWithoutDetaching([
                    $solution->id => ['price' => -$solution->price, 'quantity' => 1, 'is_active' => true],
                ]);
            }
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

    private function upsertOption(OptionGroup $group, string $name, int $price, int $cost, int $sortOrder): void
    {
        Option::updateOrCreate(
            ['option_group_id' => $group->id, 'name' => $name],
            ['price' => $price, 'cost' => $cost, 'is_active' => true, 'sort_order' => $sortOrder],
        );
    }
}
