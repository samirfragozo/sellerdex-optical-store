<?php

use App\Actions\RegisterSale;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ProductCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);
    $this->seller = User::factory()->seller()->create();
    $this->customer = Customer::factory()->create();
});

function sellCombo(array $combo, array $extraItems = []): Sale
{
    $lens = Product::where('sku', 'ML-MONOFOCAL')->first();
    $items = array_merge([
        ['product_id' => $lens->id, 'description' => $lens->name, 'quantity' => 1, 'unit_price' => 195000, 'unit_cost' => $lens->cost],
    ], $extraItems);

    return app(RegisterSale::class)->handle([
        'customer_id' => test()->customer->id,
        'document_type' => 'order',
        'items' => $items,
        'combo' => $combo,
    ], test()->seller);
}

it('auto-includes estuche, paño and a bag for a lens combo', function () {
    $sale = sellCombo(['estuche' => 'small', 'include_liquid' => false, 'include_pano' => true, 'with_exam' => false]);
    $skus = $sale->items->map(fn ($i) => optional(Product::find($i->product_id))->sku)->filter()->all();

    expect($skus)->toContain('ACC-ESTUCHE-SMALL', 'ACC-PANO')
        ->and($skus)->not->toContain('ACC-LIQUIDO')
        // total 195000 >= 215000? no -> plastic bag
        ->and($skus)->toContain('ACC-BOLSA-PLASTICO');

    // consumable lines are $0
    $estuche = $sale->items->first(fn ($i) => Product::find($i->product_id)?->sku === 'ACC-ESTUCHE-SMALL');
    expect($estuche->unit_price)->toBe(0);
});

it('omits the paño line when include_pano is false', function () {
    $sale = sellCombo(['estuche' => 'small', 'include_liquid' => false, 'include_pano' => false, 'with_exam' => false]);
    $skus = $sale->items->map(fn ($i) => optional(Product::find($i->product_id))->sku)->filter()->all();

    expect($skus)->toContain('ACC-ESTUCHE-SMALL')
        ->and($skus)->not->toContain('ACC-PANO');
});

it('uses the paper bag when the total is >= 215000', function () {
    $lens = Product::where('sku', 'ML-MONOFOCAL')->first();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => test()->customer->id,
        'document_type' => 'order',
        'items' => [
            ['product_id' => $lens->id, 'description' => $lens->name, 'quantity' => 1, 'unit_price' => 295000, 'unit_cost' => $lens->cost],
        ],
        'combo' => ['estuche' => 'large', 'include_liquid' => true, 'include_pano' => true, 'with_exam' => false],
    ], test()->seller);

    $skus = $sale->items->map(fn ($i) => Product::find($i->product_id)?->sku)->filter()->all();

    expect($skus)->toContain('ACC-ESTUCHE-LARGE', 'ACC-LIQUIDO', 'ACC-BOLSA-PAPEL');
});

it('adds the free-exam surcharge and a $0 exam line', function () {
    $sale = sellCombo(['estuche' => 'small', 'include_liquid' => false, 'include_pano' => true, 'with_exam' => true]);

    $lensLine = $sale->items->first(fn ($i) => Product::find($i->product_id)?->sku === 'ML-MONOFOCAL');
    expect($lensLine->unit_price)->toBe(215000); // 195000 + 20000

    $examLine = $sale->items->first(fn ($i) => Product::find($i->product_id)?->sku === 'SRV-EXAMEN');
    expect($examLine)->not->toBeNull()->and($examLine->unit_price)->toBe(0);
});

it('forces the montura line to $0 inside a combo and decrements its stock', function () {
    $frame = Product::where('sku', 'MNT-COMPLETA-ACETATO')->first();
    $frame->update(['stock' => 5]);

    $sale = sellCombo(
        ['estuche' => 'small', 'include_liquid' => false, 'include_pano' => true, 'with_exam' => false],
        [['product_id' => $frame->id, 'description' => $frame->name, 'quantity' => 1, 'unit_price' => 99999, 'unit_cost' => $frame->cost]],
    );

    $frameLine = $sale->items->first(fn ($i) => $i->product_id === $frame->id);
    expect($frameLine->unit_price)->toBe(0)
        ->and($frame->fresh()->stock)->toBe(4);
});
