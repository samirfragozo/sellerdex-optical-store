<?php

use App\Enums\VatRegime;
use App\Models\LensCombination;
use App\Models\LensPackage;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\User;

/** @return array{lens: LensCombination, package: LensPackage, frame: Product, sunglasses: Product, exam: Product} */
function goldenCatalog(User $seller): array
{
    // Golden values are pre-tax-agnostic: a non-responsible shop never taxes lines.
    $seller->company->update(['vat_regime' => VatRegime::NotResponsible]);
    test()->actingAs($seller);

    $category = fn (string $key) => ProductCategory::factory()->create(['key' => $key, 'name' => ucfirst($key)]);
    $cats = collect(['case', 'cloth', 'cleaning', 'pouch', 'bag', 'service', 'frame', 'sunglasses'])
        ->mapWithKeys(fn (string $key) => [$key => $category($key)]);

    $product = fn (string $cat, string $sku, string $name, int $price, int $cost) => Product::factory()->create([
        'product_category_id' => $cats[$cat]->id, 'sku' => $sku, 'name' => $name,
        'price' => $price, 'cost' => $cost, 'is_active' => true, 'is_pos_selectable' => true, 'is_stockable' => false,
    ]);

    $product('case', 'ACC-ESTUCHE-SMALL', 'Estuche pequeño', 10_000, 2_900);
    $product('case', 'ACC-ESTUCHE-LARGE', 'Estuche grande', 15_000, 4_000);
    $product('cloth', 'ACC-PANO', 'Paño', 2_000, 600);
    $product('cleaning', 'ACC-LIQUIDO', 'Líquido limpiador', 8_000, 2_000);
    $product('pouch', 'ACC-FUNDA', 'Funda', 3_000, 500);
    $product('bag', 'ACC-BOLSA-PLASTICO', 'Bolsa plástica', 200, 100);
    $product('bag', 'ACC-BOLSA-PAPEL', 'Bolsa de papel', 800, 400);
    $exam = $product('service', 'SRV-EXAMEN', 'Examen visual', 35_000, 0);

    return [
        'lens' => LensCombination::factory()->create(['price' => 180_000, 'cost' => 60_000, 'installation_price' => 0]),
        'package' => LensPackage::factory()->create(['price' => 0, 'cost' => 0]),
        'frame' => $product('frame', 'FRAME', 'Montura Golden', 150_000, 60_000),
        'sunglasses' => $product('sunglasses', 'SUNGLASSES', 'Gafas de sol Golden', 250_000, 100_000),
        'exam' => $exam,
    ];
}

/** @return list<array{group: string|null, sku: string|null, description: string, qty: int, price: int}> */
function goldenLines(Sale $sale): array
{
    return $sale->items()->with('product')->orderBy('id')->get()
        ->map(fn ($i) => [
            'group' => $i->group_key,
            'sku' => $i->product?->sku,
            'description' => $i->description,
            'qty' => (int) $i->quantity,
            'price' => (int) $i->unit_price,
        ])->all();
}
