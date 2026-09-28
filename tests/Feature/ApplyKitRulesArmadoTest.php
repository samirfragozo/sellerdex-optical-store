<?php

use App\Actions\RegisterSale;
use App\Enums\ArmadoFramePriceMode;
use App\Enums\KitPriceMode;
use App\Enums\KitTrigger;
use App\Models\Customer;
use App\Models\KitSlot;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\ReferenceKit;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Support/GoldenCatalog.php';

beforeEach(function () {
    $this->seller = User::factory()->seller()->create();
    $this->catalog = goldenCatalog($this->seller);
    $this->customer = Customer::factory()->create();
});

/** @return array<string, mixed> */
function kitLens(array $catalog): array
{
    return [
        'description' => 'Lente formulado',
        'lens_type_id' => $catalog['lens']->lens_type_id,
        'lens_technology_id' => $catalog['lens']->lens_technology_id,
        'lens_material_id' => $catalog['lens']->lens_material_id,
        'treatment_ids' => [],
    ];
}

/** @param  array<string, mixed>  $armado */
function kitSellArmado(array $armado): Sale
{
    return app(RegisterSale::class)->handle([
        'customer_id' => test()->customer->id,
        'document_type' => 'order',
        'armados' => [$armado],
    ], test()->seller);
}

it('adds nothing extra for a company without combo slots', function () {
    $sale = kitSellArmado(['lens' => kitLens($this->catalog), 'own_frame' => true]);

    expect($sale->items)->toHaveCount(1)->and($sale->total)->toBe(180_000);
});

it('rejects swapping a slot product for one outside its category', function () {
    ReferenceKit::installFor($this->seller->company);
    $caseSlot = KitSlot::where('trigger', KitTrigger::Armado)->get()->firstWhere(fn ($s) => $s->slotCategory->key === 'case');
    $foreign = Product::where('sku', 'ACC-LIQUIDO')->sole();

    expect(fn () => kitSellArmado([
        'lens' => kitLens($this->catalog), 'own_frame' => true,
        'slots' => [['kit_slot_id' => $caseSlot->id, 'product_id' => $foreign->id, 'selected' => true]],
    ]))->toThrow(ValidationException::class);
});

it('charges the frame by the company frame pricing mode', function () {
    $this->seller->company->update(['armado_frame_price_mode' => ArmadoFramePriceMode::DiscountPercent, 'armado_frame_discount_percent' => 20]);
    $frame = $this->catalog['frame'];
    $frame->update(['is_stockable' => true, 'stock' => 5]);

    $sale = kitSellArmado([
        'lens' => kitLens($this->catalog),
        'frame' => ['product_id' => $frame->id, 'description' => $frame->name, 'unit_price' => 150_000],
    ]);

    expect($sale->items->firstWhere('product_id', $frame->id)->unit_price)->toBe(120_000)
        ->and($sale->total)->toBe(300_000)
        ->and($frame->fresh()->stock)->toBe(4);
});

it('prices a charged slot and a discounted slot', function () {
    ReferenceKit::installFor($this->seller->company);
    $slots = KitSlot::where('trigger', KitTrigger::Armado)->orderBy('sort_order')->get();
    $slots[1]->update(['price_mode' => KitPriceMode::Normal]);
    $slots[3]->update(['price_mode' => KitPriceMode::DiscountPercent, 'price_value' => 50]);

    $sale = kitSellArmado([
        'lens' => kitLens($this->catalog), 'own_frame' => true,
        'slots' => [['kit_slot_id' => $slots[3]->id, 'selected' => true]],
    ]);

    expect($sale->items->firstWhere(fn ($i) => $i->product?->sku === 'ACC-ESTUCHE-SMALL')->unit_price)->toBe(10_000)
        ->and($sale->items->firstWhere(fn ($i) => $i->product?->sku === 'ACC-LIQUIDO')->unit_price)->toBe(4_000);
});

it('raises the lens price with an added_to_lens slot and accepts paying that total', function () {
    ReferenceKit::installFor($this->seller->company);
    $exam = KitSlot::where('trigger', KitTrigger::Armado)->get()->firstWhere(fn ($s) => $s->slotCategory->key === 'service');
    $method = PaymentMethod::factory()->create(['is_active' => true]);

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => $this->customer->id,
        'document_type' => 'order',
        'armados' => [['lens' => kitLens($this->catalog), 'own_frame' => true, 'slots' => [['kit_slot_id' => $exam->id, 'selected' => true]]]],
        'payments' => [['payment_method_id' => $method->id, 'amount' => 200_000]],
    ], $this->seller);

    expect($sale->items->first(fn ($i) => $i->isLens())->unit_price)->toBe(200_000)
        ->and($sale->balance)->toBe(0);
});

it('rejects an inactive product of the slot category in a selection', function () {
    ReferenceKit::installFor($this->seller->company);
    $caseSlot = KitSlot::where('trigger', KitTrigger::Armado)->get()->firstWhere(fn ($s) => $s->slotCategory->key === 'case');
    $inactive = Product::where('sku', 'ACC-ESTUCHE-LARGE')->sole();
    $inactive->update(['is_active' => false]);

    expect(fn () => kitSellArmado([
        'lens' => kitLens($this->catalog), 'own_frame' => true,
        'slots' => [['kit_slot_id' => $caseSlot->id, 'product_id' => $inactive->id, 'selected' => true]],
    ]))->toThrow(ValidationException::class);
});

it('skips a slot whose default product is inactive', function () {
    ReferenceKit::installFor($this->seller->company);
    Product::where('sku', 'ACC-ESTUCHE-SMALL')->sole()->update(['is_active' => false]);

    $sale = kitSellArmado(['lens' => kitLens($this->catalog), 'own_frame' => true]);

    expect($sale->items->where('group_key', 'g1')->pluck('product.sku')->all())->toBe([null, 'ACC-PANO']);
});

it('raises only the lens of the armado that selected an added_to_lens slot', function () {
    ReferenceKit::installFor($this->seller->company);
    $exam = KitSlot::where('trigger', KitTrigger::Armado)->get()->firstWhere(fn ($s) => $s->slotCategory->key === 'service');

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => $this->customer->id,
        'document_type' => 'order',
        'armados' => [
            ['lens' => kitLens($this->catalog), 'own_frame' => true],
            ['lens' => kitLens($this->catalog), 'own_frame' => true, 'slots' => [['kit_slot_id' => $exam->id, 'selected' => true]]],
        ],
    ], $this->seller);

    $lenses = $sale->items->filter(fn ($i) => $i->isLens())->pluck('unit_price', 'group_key')->all();

    expect($lenses)->toBe(['g1' => 180_000, 'g2' => 200_000]);
});
