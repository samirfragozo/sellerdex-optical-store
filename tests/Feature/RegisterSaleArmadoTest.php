<?php

use App\Actions\RegisterSale;
use App\Models\Customer;
use App\Models\LensCombination;
use App\Models\LensTreatment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tax;
use App\Models\User;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ProductCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Lens catalog rows are company-scoped (BelongsToCompany), so both the fixtures
    // and RegisterSale must run as the same authenticated seller.
    $this->seller = User::factory()->seller()->create();
    $this->actingAs($this->seller);
});

function seedCatalog(): void
{
    test()->seed(ProductCategorySeeder::class);
    test()->seed(ProductCatalogSeeder::class);
}

/**
 * A valid `armados.*.lens` payload backed by a freshly created catalog combination,
 * and treatment. `$prices` overrides the combination's own price/cost.
 *
 * @param  array<string,int>  $prices
 * @return array<string,mixed>
 */
function lensPayload(array $prices = [], array $extra = []): array
{
    $combination = LensCombination::factory()->create($prices);

    return array_merge([
        'description' => 'Lente formulado',
        'quantity' => 1,
        'lens_type_id' => $combination->lens_type_id,
        'lens_technology_id' => $combination->lens_technology_id,
        'lens_material_id' => $combination->lens_material_id,
        'treatment_ids' => [],
    ], $extra);
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

it('registra un lente con su configuración y tratamientos resueltos', function () {
    $combination = LensCombination::factory()->create(['cost' => 60000, 'price' => 180000, 'installation_price' => 3000]);
    $treatment = LensTreatment::factory()->create(['price' => 50000, 'cost' => 20000]);

    $sale = (new RegisterSale)->handle([
        'document_type' => 'order',
        'armados' => [[
            'lens' => [
                'description' => 'Lente formulado',
                'quantity' => 1,
                'lens_type_id' => $combination->lens_type_id,
                'lens_technology_id' => $combination->lens_technology_id,
                'lens_material_id' => $combination->lens_material_id,
                'treatment_ids' => [$treatment->id],
            ],
            'own_frame' => true,
        ]],
    ], $this->seller);

    $lensItem = $sale->items->first(fn ($i) => $i->isLens());

    expect($lensItem->unit_price)->toBe(180000 + 3000 + 50000)
        ->and($lensItem->unit_cost)->toBe(60000 + 20000)
        ->and($lensItem->lensConfig->treatments()->count())->toBe(1)
        ->and($lensItem->lensOrder)->not->toBeNull();
});

it('builds two armados, each with its own grouped combo lines', function () {
    seedCatalog();
    $frame = Product::where('sku', 'MNT-COMPLETA-ACETATO')->first();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [
            [
                'lens' => lensPayload(),
                'frame' => ['product_id' => $frame->id, 'description' => $frame->name, 'unit_price' => $frame->price],
                'combo' => ['with_exam' => false, 'estuche' => 'small', 'include_liquid' => true, 'include_pano' => true],
            ],
            [
                'lens' => lensPayload(),
                'own_frame' => true,
                'combo' => ['with_exam' => false, 'estuche' => 'large', 'include_liquid' => false, 'include_pano' => true],
            ],
        ],
    ], $this->seller);

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

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => lensPayload(['price' => 100000, 'installation_price' => 0]),
            'own_frame' => true,
            'combo' => ['with_exam' => true, 'estuche' => 'small', 'include_liquid' => false, 'include_pano' => true],
        ]],
    ], $this->seller);

    $lensLine = $sale->items->first(fn ($i) => $i->isLens());
    expect($lensLine->unit_price)->toBe(100000 + 20000)
        ->and($sale->items->contains(fn ($i) => Product::find($i->product_id)?->sku === 'SRV-EXAMEN'))->toBeTrue();
});

it('lets a seller-entered price_override win over the resolved catalog price', function () {
    seedCatalog();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => lensPayload(['price' => 250000], ['price_override' => 90000]),
            'own_frame' => true,
            'combo' => ['with_exam' => false, 'estuche' => 'small', 'include_liquid' => false, 'include_pano' => true],
        ]],
    ], $this->seller);

    $lensLine = $sale->items->first(fn ($i) => $i->isLens());
    expect($lensLine->unit_price)->toBe(90000);
});

it('taxes armado frame lines with the frame\'s tax', function () {
    seedCatalog();
    $frame = Product::where('sku', 'MNT-COMPLETA-ACETATO')->first();
    $frame->update(['tax_id' => Tax::factory()->create(['name' => 'IVA 19%', 'rate' => 19])->id]);

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => lensPayload(),
            'frame' => ['product_id' => $frame->id, 'description' => $frame->name, 'unit_price' => $frame->price],
            'combo' => ['with_exam' => false, 'estuche' => 'small', 'include_liquid' => false, 'include_pano' => true],
        ]],
    ], $this->seller);

    $frameLine = $sale->items->firstWhere('product_id', $frame->id);

    expect($frameLine->tax_name)->toBe('IVA 19%')
        ->and($frameLine->tax_amount)->toBe((int) round($frameLine->line_total - $frameLine->line_total / 1.19));
});

it('mixes an armado with a standalone product line', function () {
    seedCatalog();
    $accessory = Product::where('sku', 'ACC-LIQUIDO')->first();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => lensPayload(),
            'own_frame' => true,
            'combo' => ['with_exam' => false, 'estuche' => 'small', 'include_liquid' => false, 'include_pano' => true],
        ]],
        'products' => [
            ['product_id' => $accessory->id, 'description' => $accessory->name, 'quantity' => 2, 'unit_price' => $accessory->price],
        ],
    ], $this->seller);

    $accessoryLine = $sale->items->where('product_id', $accessory->id)->firstWhere('group_key', null);
    expect($accessoryLine)->not->toBeNull()
        ->and($accessoryLine->quantity)->toBe(2);
});

it('adds a single global bag for the whole sale', function () {
    seedCatalog();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => lensPayload(['price' => 125000]),
            'own_frame' => true,
            'combo' => ['with_exam' => false, 'estuche' => 'small', 'include_liquid' => false, 'include_pano' => true],
        ]],
    ], $this->seller);

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
    ], $this->seller);

    expect($sale->items->contains(fn ($i) => Product::find($i->product_id)?->sku === 'ACC-FUNDA'))->toBeTrue()
        ->and($sale->items->firstWhere('product_id', $frame->id)->quantity)->toBe(1);
});
