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
    $lensA = Product::where('sku', 'ML-001')->first();   // Monofocal 1.56
    $lensB = Product::where('sku', 'ML-052')->first();   // Progresivo 1.56
    $frame = Product::where('sku', 'MNT-COMPLETAS-ACETATO')->first();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [
            [
                'lens' => ['product_id' => $lensA->id, 'description' => $lensA->name, 'unit_price' => $lensA->price],
                'frame' => ['product_id' => $frame->id, 'description' => $frame->name, 'unit_price' => $frame->price],
                'combo' => ['with_exam' => false, 'forro' => 'small', 'include_liquid' => true],
            ],
            [
                'lens' => ['product_id' => $lensB->id, 'description' => $lensB->name, 'unit_price' => $lensB->price],
                'own_frame' => true,
                'combo' => ['with_exam' => false, 'forro' => 'large', 'include_liquid' => false],
            ],
        ],
    ], User::factory()->seller()->create());

    // Each armado gets its own group_key; the frame in armado 1 is dropped to $0.
    $groups = $sale->items->pluck('group_key')->filter()->unique();
    expect($groups)->toHaveCount(2);

    $frameLine = $sale->items->firstWhere('product_id', $frame->id);
    expect($frameLine->unit_price)->toBe(0);

    // Armado 1 has a small forro + liquid; armado 2 has a large forro + no liquid.
    $smallForro = Product::where('sku', 'ACC-FORRO-SMALL')->first();
    $largeForro = Product::where('sku', 'ACC-FORRO-LARGE')->first();
    expect($sale->items->where('product_id', $smallForro->id))->toHaveCount(1);
    expect($sale->items->where('product_id', $largeForro->id))->toHaveCount(1);
});

it('adds the free exam surcharge per armado when requested', function () {
    seedCatalog();
    $lens = Product::where('sku', 'ML-001')->first();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => ['product_id' => $lens->id, 'description' => $lens->name, 'unit_price' => $lens->price],
            'own_frame' => true,
            'combo' => ['with_exam' => true, 'forro' => 'small', 'include_liquid' => false],
        ]],
    ], User::factory()->seller()->create());

    $lensLine = $sale->items->firstWhere('product_id', $lens->id);
    expect($lensLine->unit_price)->toBe($lens->price + 20000)
        ->and($sale->items->contains(fn ($i) => Product::find($i->product_id)?->sku === 'SRV-EXAMEN'))->toBeTrue();
});

it('mixes an armado with a standalone product line', function () {
    seedCatalog();
    $lens = Product::where('sku', 'ML-001')->first();
    $accessory = Product::where('sku', 'ACC-LIQUIDO')->first();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => ['product_id' => $lens->id, 'description' => $lens->name, 'unit_price' => $lens->price],
            'own_frame' => true,
            'combo' => ['with_exam' => false, 'forro' => 'small', 'include_liquid' => false],
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
    $lens = Product::where('sku', 'ML-052')->first(); // 125k

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => ['product_id' => $lens->id, 'description' => $lens->name, 'unit_price' => $lens->price],
            'own_frame' => true,
            'combo' => ['with_exam' => false, 'forro' => 'small', 'include_liquid' => false],
        ]],
    ], User::factory()->seller()->create());

    $bags = $sale->items->filter(fn ($i) => str_starts_with(Product::find($i->product_id)?->sku ?? '', 'ACC-BOLSA-'));
    expect($bags)->toHaveCount(1);
});
