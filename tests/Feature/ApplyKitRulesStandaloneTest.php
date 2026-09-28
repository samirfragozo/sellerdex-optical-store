<?php

use App\Actions\RegisterSale;
use App\Enums\KitPriceMode;
use App\Enums\KitTrigger;
use App\Models\Customer;
use App\Models\KitSlot;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\User;
use App\Support\ReferenceKit;

require_once __DIR__.'/../Support/GoldenCatalog.php';

beforeEach(function () {
    $this->seller = User::factory()->seller()->create();
    $this->catalog = goldenCatalog($this->seller);
});

function kitSellProducts(array $lines): Sale
{
    return app(RegisterSale::class)->handle(['document_type' => 'order', 'products' => $lines], test()->seller);
}

function kitLine(Product $p, int $price, int $qty = 1): array
{
    return ['product_id' => $p->id, 'description' => $p->name, 'quantity' => $qty, 'unit_price' => $price];
}

it('adds nothing for a company without combo slots', function () {
    $sale = kitSellProducts([kitLine($this->catalog['frame'], 150_000)]);

    expect($sale->items)->toHaveCount(1);
});

it('uses the default bag one peso below the threshold and the upgrade exactly at it', function () {
    ReferenceKit::installFor($this->seller->company);
    $item = Product::factory()->create(['product_category_id' => ProductCategory::keyed('cloth')->id, 'is_active' => true]);

    $below = kitSellProducts([kitLine($item, 214_999)]);
    $at = kitSellProducts([kitLine($item, 215_000)]);

    expect($below->items->pluck('product.sku'))->toContain('ACC-BOLSA-PLASTICO')->not->toContain('ACC-BOLSA-PAPEL')
        ->and($at->items->pluck('product.sku'))->toContain('ACC-BOLSA-PAPEL')->not->toContain('ACC-BOLSA-PLASTICO');
});

it('adds a specific-product slot once per sale (replaces product additions)', function () {
    $contactLens = Product::factory()->create(['price' => 90_000, 'is_active' => true]);
    $solution = Product::where('sku', 'ACC-LIQUIDO')->sole();
    KitSlot::factory()->create([
        'trigger' => KitTrigger::Product, 'trigger_product_id' => $contactLens->id,
        'slot_category_id' => $solution->product_category_id, 'default_product_id' => $solution->id,
        'price_mode' => KitPriceMode::DiscountPercent, 'price_value' => 50, 'quantity' => 1,
    ]);

    $sale = kitSellProducts([kitLine($contactLens, 90_000, 2)]);
    $solutionLines = $sale->items->where('product_id', $solution->id);

    // Non-armado combos are always free; the POS has no way to show their price yet.
    expect($solutionLines)->toHaveCount(1)->and($solutionLines->first()->unit_price)->toBe(0);
});

it('gives a sale-trigger slot for free even when it is stored as charged', function () {
    ReferenceKit::installFor($this->seller->company);
    KitSlot::where('trigger', KitTrigger::Sale)->update(['price_mode' => KitPriceMode::Normal]);
    KitSlot::where('trigger', KitTrigger::Category)->update(['price_mode' => KitPriceMode::DiscountPercent, 'price_value' => 10]);

    $sale = kitSellProducts([kitLine($this->catalog['frame'], 150_000)]);

    expect($sale->items->where('product_id', '!=', $this->catalog['frame']->id)->pluck('unit_price', 'product.sku')->all())
        ->toBe(['ACC-FUNDA' => 0, 'ACC-BOLSA-PLASTICO' => 0])
        ->and($sale->total)->toBe(150_000);
});

it('does not add a standalone-category slot for a frame inside an armado', function () {
    ReferenceKit::installFor($this->seller->company);
    $frame = $this->catalog['frame'];

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => [
                'description' => 'Lente', 'treatment_ids' => [],
                'lens_type_id' => $this->catalog['lens']->lens_type_id,
                'lens_technology_id' => $this->catalog['lens']->lens_technology_id,
                'lens_material_id' => $this->catalog['lens']->lens_material_id,
            ],
            'frame' => ['product_id' => $frame->id, 'description' => $frame->name, 'unit_price' => 150_000],
        ]],
    ], $this->seller);

    expect($sale->items->pluck('product.sku'))->not->toContain('ACC-FUNDA');
});

it('registers the sale without a bag when the bag slot product was deleted', function () {
    ReferenceKit::installFor($this->seller->company);
    // The model guard keeps slot products; a query delete stands in for older data.
    Product::where('sku', 'ACC-BOLSA-PLASTICO')->delete();

    $sale = kitSellProducts([kitLine($this->catalog['sunglasses'], 100_000)]);

    expect($sale->items->pluck('product.sku')->all())->toBe(['SUNGLASSES']);
});

it('falls back to the default bag when the upgrade product is inactive', function () {
    ReferenceKit::installFor($this->seller->company);
    Product::where('sku', 'ACC-BOLSA-PAPEL')->sole()->update(['is_active' => false]);

    $sale = kitSellProducts([kitLine($this->catalog['sunglasses'], 250_000)]);

    expect($sale->items->pluck('product.sku')->all())->toBe(['SUNGLASSES', 'ACC-BOLSA-PLASTICO']);
});

it('skips a category slot whose default product is inactive', function () {
    ReferenceKit::installFor($this->seller->company);
    Product::where('sku', 'ACC-FUNDA')->sole()->update(['is_active' => false]);

    $sale = kitSellProducts([kitLine($this->catalog['frame'], 150_000)]);

    expect($sale->items->pluck('product.sku')->all())->toBe(['FRAME', 'ACC-BOLSA-PLASTICO']);
});

it('gives the pouch only to the loose frame in a sale that also has an armado frame', function () {
    ReferenceKit::installFor($this->seller->company);
    $frame = $this->catalog['frame'];

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => [
                'description' => 'Lente', 'treatment_ids' => [],
                'lens_type_id' => $this->catalog['lens']->lens_type_id,
                'lens_technology_id' => $this->catalog['lens']->lens_technology_id,
                'lens_material_id' => $this->catalog['lens']->lens_material_id,
            ],
            'frame' => ['product_id' => $frame->id, 'description' => $frame->name, 'unit_price' => 150_000],
        ]],
        'products' => [kitLine($frame, 150_000)],
    ], $this->seller);

    $pouches = $sale->items->filter(fn ($i) => $i->product?->sku === 'ACC-FUNDA');

    expect($pouches)->toHaveCount(1)->and($pouches->first()->group_key)->toBeNull();
});
