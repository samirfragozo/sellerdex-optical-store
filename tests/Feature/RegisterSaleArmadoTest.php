<?php

use App\Actions\RegisterSale;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ProductCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function seedCatalog(): void
{
    test()->seed(ProductCategorySeeder::class);
    test()->seed(ProductCatalogSeeder::class);
}

/** Picks the first (zero-delta) option in every required group — resolves to the base lens price. */
function defaultLensOptionIds(Product $lens): array
{
    return $lens->optionGroups->map(fn ($g) => $g->options->first()->id)->all();
}

it('has a nullable group_key column on sale_items', function () {
    expect(Schema::hasColumn('sale_items', 'group_key'))->toBeTrue();
});

it('persists group_key on a sale item', function () {
    $sale = Sale::factory()->create();
    $item = $sale->items()->create([
        'group_key' => 'g1',
        'description' => 'Línea de prueba',
        'quantity' => 1,
        'unit_price' => 0,
    ]);

    expect($item->fresh()->group_key)->toBe('g1');
});

it('builds two armados, each with its own grouped combo lines', function () {
    seedCatalog();
    $lensA = Product::where('sku', 'ML-MONOFOCAL')->first();
    $lensB = Product::where('sku', 'ML-PROGRESIVO')->first();
    $frame = Product::where('sku', 'MNT-COMPLETA-ACETATO')->first();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [
            [
                'lens' => ['product_id' => $lensA->id, 'description' => $lensA->name, 'option_ids' => defaultLensOptionIds($lensA)],
                'frame' => ['product_id' => $frame->id, 'description' => $frame->name, 'unit_price' => $frame->price],
                'combo' => ['with_exam' => false, 'estuche' => 'small', 'include_liquid' => true, 'include_pano' => true],
            ],
            [
                'lens' => ['product_id' => $lensB->id, 'description' => $lensB->name, 'option_ids' => defaultLensOptionIds($lensB)],
                'own_frame' => true,
                'combo' => ['with_exam' => false, 'estuche' => 'large', 'include_liquid' => false, 'include_pano' => true],
            ],
        ],
    ], User::factory()->seller()->create());

    // Each armado gets its own group_key; the frame in armado 1 is dropped to $0.
    $groups = $sale->items->pluck('group_key')->filter()->unique();
    expect($groups)->toHaveCount(2);

    $frameLine = $sale->items->firstWhere('product_id', $frame->id);
    expect($frameLine->unit_price)->toBe(0);

    // Armado 1 has a small estuche + liquid; armado 2 has a large estuche + no liquid.
    $smallEstuche = Product::where('sku', 'ACC-ESTUCHE-SMALL')->first();
    $largeEstuche = Product::where('sku', 'ACC-ESTUCHE-LARGE')->first();
    expect($sale->items->where('product_id', $smallEstuche->id))->toHaveCount(1);
    expect($sale->items->where('product_id', $largeEstuche->id))->toHaveCount(1);
});

it('adds the free exam surcharge per armado when requested', function () {
    seedCatalog();
    $lens = Product::where('sku', 'ML-MONOFOCAL')->first();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => ['product_id' => $lens->id, 'description' => $lens->name, 'option_ids' => defaultLensOptionIds($lens)],
            'own_frame' => true,
            'combo' => ['with_exam' => true, 'estuche' => 'small', 'include_liquid' => false, 'include_pano' => true],
        ]],
    ], User::factory()->seller()->create());

    $lensLine = $sale->items->firstWhere('product_id', $lens->id);
    expect($lensLine->unit_price)->toBe($lens->price + 20000)
        ->and($sale->items->contains(fn ($i) => Product::find($i->product_id)?->sku === 'SRV-EXAMEN'))->toBeTrue();
});

it('does not tax armado lens or frame lines even when the product has a tax_rate', function () {
    seedCatalog();
    $lens = Product::where('sku', 'ML-MONOFOCAL')->first();
    $frame = Product::where('sku', 'MNT-COMPLETA-ACETATO')->first();
    $lens->update(['tax_rate' => 19]);
    $frame->update(['tax_rate' => 19]);

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => ['product_id' => $lens->id, 'description' => $lens->name, 'option_ids' => defaultLensOptionIds($lens)],
            'frame' => ['product_id' => $frame->id, 'description' => $frame->name, 'unit_price' => $frame->price],
            'combo' => ['with_exam' => false, 'estuche' => 'small', 'include_liquid' => false, 'include_pano' => true],
        ]],
    ], User::factory()->seller()->create());

    $lensLine = $sale->items->firstWhere('product_id', $lens->id);
    $frameLine = $sale->items->firstWhere('product_id', $frame->id);

    expect($lensLine->tax_amount)->toBe(0)
        ->and($frameLine->tax_amount)->toBe(0);
});

it('mixes an armado with a standalone product line', function () {
    seedCatalog();
    $lens = Product::where('sku', 'ML-MONOFOCAL')->first();
    $accessory = Product::where('sku', 'ACC-LIQUIDO')->first();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => ['product_id' => $lens->id, 'description' => $lens->name, 'option_ids' => defaultLensOptionIds($lens)],
            'own_frame' => true,
            'combo' => ['with_exam' => false, 'estuche' => 'small', 'include_liquid' => false, 'include_pano' => true],
        ]],
        'products' => [
            ['product_id' => $accessory->id, 'description' => $accessory->name, 'quantity' => 2, 'unit_price' => $accessory->price],
        ],
    ], User::factory()->seller()->create());

    $accessoryLine = $sale->items->where('product_id', $accessory->id)->firstWhere('group_key', null);
    expect($accessoryLine)->not->toBeNull()
        ->and($accessoryLine->quantity)->toBe(2);
});

it('adds a single global bag for the whole sale', function () {
    seedCatalog();
    $lens = Product::where('sku', 'ML-PROGRESIVO')->first(); // 125k

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => ['product_id' => $lens->id, 'description' => $lens->name, 'option_ids' => defaultLensOptionIds($lens)],
            'own_frame' => true,
            'combo' => ['with_exam' => false, 'estuche' => 'small', 'include_liquid' => false, 'include_pano' => true],
        ]],
    ], User::factory()->seller()->create());

    $bags = $sale->items->filter(fn ($i) => str_starts_with(Product::find($i->product_id)?->sku ?? '', 'ACC-BOLSA-'));
    expect($bags)->toHaveCount(1);
});

it('sells standalone products via the new payload and adds a funda for a frame', function () {
    seedCatalog();
    $frame = Product::where('sku', 'MNT-COMPLETA-ACETATO')->first();
    $frame->update(['stock' => 5]);

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [],
        'products' => [
            ['product_id' => $frame->id, 'description' => $frame->name, 'unit_price' => $frame->price, 'quantity' => 1],
        ],
    ], User::factory()->seller()->create());

    expect($sale->items->contains(fn ($i) => Product::find($i->product_id)?->sku === 'ACC-FUNDA'))->toBeTrue()
        ->and($sale->items->firstWhere('product_id', $frame->id)->quantity)->toBe(1);
});
