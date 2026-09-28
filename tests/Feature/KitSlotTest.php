<?php

use App\Enums\ArmadoFramePriceMode;
use App\Enums\KitPriceMode;
use App\Enums\KitTrigger;
use App\Models\KitSlot;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\ReferenceKit;
use Illuminate\Database\QueryException;

require_once __DIR__.'/../Support/GoldenCatalog.php';

beforeEach(function () {
    $this->seller = User::factory()->admin()->create();
    goldenCatalog($this->seller);
});

it('prices a slot product by mode', function (KitPriceMode $mode, float $value, int $expected) {
    $product = Product::factory()->create(['price' => 10_000]);
    $slot = KitSlot::factory()->make(['price_mode' => $mode, 'price_value' => $value]);

    expect($slot->unitPriceFor($product))->toBe($expected);
})->with([
    [KitPriceMode::Free, 0, 0],
    [KitPriceMode::Normal, 0, 10_000],
    [KitPriceMode::DiscountPercent, 25, 7_500],
    [KitPriceMode::AddedToLens, 20_000, 0],
]);

it('adds the configured amount to the lens only in added_to_lens mode', function () {
    expect(KitSlot::factory()->make(['price_mode' => KitPriceMode::AddedToLens, 'price_value' => 20_000])->lensSurcharge())->toBe(20_000)
        ->and(KitSlot::factory()->make(['price_mode' => KitPriceMode::Free, 'price_value' => 20_000])->lensSurcharge())->toBe(0);
});

it('never allows two slots of the same category in one combo', function () {
    $case = ProductCategory::keyed('case');
    $product = Product::where('sku', 'ACC-ESTUCHE-SMALL')->sole();
    $make = fn () => KitSlot::factory()->create(['trigger' => KitTrigger::Armado, 'slot_category_id' => $case->id, 'default_product_id' => $product->id]);

    $make();

    expect($make)->toThrow(QueryException::class);
});

it('installs the reference kit that reproduces the old hardcoded combo', function () {
    ReferenceKit::installFor($this->seller->company);
    ReferenceKit::installFor($this->seller->company); // idempotent

    $slots = KitSlot::with(['slotCategory', 'defaultProduct', 'upgradeProduct', 'triggerCategory'])->orderBy('sort_order')->get();
    $armado = $slots->where('trigger', KitTrigger::Armado)->values();

    expect($armado->map(fn ($s) => $s->slotCategory->key)->all())->toBe(['service', 'case', 'cloth', 'cleaning'])
        ->and($armado[0]->price_mode)->toBe(KitPriceMode::AddedToLens)
        ->and((int) $armado[0]->price_value)->toBe(20_000)
        ->and($armado[0]->defaultProduct->sku)->toBe('SRV-EXAMEN')
        ->and([$armado[0]->is_optional, $armado[0]->is_preselected])->toBe([true, false])
        ->and($armado[1]->defaultProduct->sku)->toBe('ACC-ESTUCHE-SMALL')
        ->and($armado[1]->is_optional)->toBeFalse()
        ->and([$armado[2]->is_optional, $armado[2]->is_preselected])->toBe([true, true])
        ->and([$armado[3]->is_optional, $armado[3]->is_preselected])->toBe([true, false]);

    $pouch = $slots->firstWhere('trigger', KitTrigger::Category);
    expect($pouch->triggerCategory->key)->toBe('frame')->and($pouch->defaultProduct->sku)->toBe('ACC-FUNDA');

    $bag = $slots->firstWhere('trigger', KitTrigger::Sale);
    expect($bag->defaultProduct->sku)->toBe('ACC-BOLSA-PLASTICO')
        ->and($bag->upgradeProduct->sku)->toBe('ACC-BOLSA-PAPEL')
        ->and($bag->upgrade_min_total)->toBe(215_000)
        ->and($bag->productForMerchTotal(214_999)->sku)->toBe('ACC-BOLSA-PLASTICO')
        ->and($bag->productForMerchTotal(215_000)->sku)->toBe('ACC-BOLSA-PAPEL');

    expect($slots)->toHaveCount(6)
        ->and($this->seller->company->fresh()->armado_frame_price_mode)->toBe(ArmadoFramePriceMode::Included);
});

it('picks the exam as the service default regardless of name casing', function () {
    Product::where('sku', 'SRV-EXAMEN')->sole()->update(['name' => 'Examen Visual']);
    Product::factory()->create(['product_category_id' => ProductCategory::keyed('service')->id, 'price' => 1_000, 'is_active' => true]);

    ReferenceKit::installFor($this->seller->company);

    $service = KitSlot::where('trigger', KitTrigger::Armado)->get()->firstWhere(fn ($s) => $s->slotCategory->key === 'service');
    expect($service->defaultProduct->sku)->toBe('SRV-EXAMEN');
});

it('prices the armado frame by the company setting', function () {
    $company = $this->seller->company;

    expect($company->armadoFrameUnitPrice(150_000))->toBe(0);
    $company->update(['armado_frame_price_mode' => ArmadoFramePriceMode::DiscountPercent, 'armado_frame_discount_percent' => 30]);
    expect($company->fresh()->armadoFrameUnitPrice(150_000))->toBe(105_000);
    $company->update(['armado_frame_price_mode' => ArmadoFramePriceMode::Normal]);
    expect($company->fresh()->armadoFrameUnitPrice(150_000))->toBe(150_000);
});

it('keeps a category that a kit slot uses', function () {
    ReferenceKit::installFor($this->seller->company);
    $case = ProductCategory::keyed('case');

    $case->delete();

    expect(ProductCategory::whereKey($case->id)->exists())->toBeTrue();
});

it('keeps a product that a combo slot uses, soft or force deleted', function (string $sku) {
    ReferenceKit::installFor($this->seller->company);
    $contactLens = Product::factory()->create();
    KitSlot::factory()->create([
        'trigger' => KitTrigger::Product, 'trigger_product_id' => $contactLens->id,
        'slot_category_id' => ProductCategory::keyed('cloth')->id, 'default_product_id' => Product::where('sku', 'ACC-PANO')->value('id'),
    ]);
    $product = $sku === 'trigger' ? $contactLens : Product::where('sku', $sku)->sole();

    expect($product->isDeletable())->toBeFalse()
        ->and($product->delete())->toBeFalse()
        ->and($product->forceDelete())->toBeFalse()
        ->and(Product::whereKey($product->id)->exists())->toBeTrue();
})->with(['default' => 'ACC-FUNDA', 'upgrade' => 'ACC-BOLSA-PAPEL', 'trigger' => 'trigger']);

it('still deletes a product no combo uses', function () {
    ReferenceKit::installFor($this->seller->company);
    $product = Product::factory()->create();

    expect($product->isDeletable())->toBeTrue()
        ->and($product->delete())->toBeTrue();
});

it('translates every kit enum', function () {
    foreach ([...KitTrigger::cases(), ...KitPriceMode::cases(), ...ArmadoFramePriceMode::cases()] as $case) {
        expect($case->label())->not->toStartWith('app.');
    }
});
