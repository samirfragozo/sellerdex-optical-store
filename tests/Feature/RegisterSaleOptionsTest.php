<?php

use App\Actions\RegisterSale;
use App\Enums\LensOrderStatus;
use App\Models\Customer;
use App\Models\LensCombination;
use App\Models\LensPackage;
use App\Models\LensTreatment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seller = User::factory()->seller()->create();
    // Lens catalog rows are company-scoped (BelongsToCompany), so the fixtures below
    // and RegisterSale itself must run as the same authenticated seller.
    $this->actingAs($this->seller);
    $this->customer = Customer::factory()->create();

    $this->combination = LensCombination::factory()->create([
        'cost' => 10000,
        'price' => 20000,
        'installation_price' => 5000,
    ]);
    $this->package = LensPackage::factory()->create(['price' => 30000, 'cost' => 10000]);
    $this->treatment = LensTreatment::factory()->create(['price' => 70000, 'cost' => 20000]);
});

/**
 * @param  array<string,mixed>  $extra
 * @return array<string,mixed>
 */
function armadoLens(array $extra = []): array
{
    return array_merge([
        'description' => 'Lente formulado',
        'lens_type_id' => test()->combination->lens_type_id,
        'lens_technology_id' => test()->combination->lens_technology_id,
        'lens_material_id' => test()->combination->lens_material_id,
        'lens_package_id' => test()->package->id,
        'treatment_ids' => [test()->treatment->id],
    ], $extra);
}

it('prices an armado lens from its resolved configuration and snapshots it', function () {
    $sale = app(RegisterSale::class)->handle([
        'customer_id' => $this->customer->id,
        'document_type' => 'order',
        'armados' => [['lens' => armadoLens()]],
    ], $this->seller);

    $lensItem = $sale->items->first(fn ($i) => $i->isLens());

    expect($lensItem->product_id)->toBeNull()
        ->and($lensItem->unit_price)->toBe(125000)
        ->and($lensItem->unit_cost)->toBe(40000)
        ->and($lensItem->lensConfig->lens_combination_id)->toBe($this->combination->id)
        ->and($lensItem->lensConfig->type_name)->toBe($this->combination->lensType->name)
        ->and($lensItem->lensConfig->technology_name)->toBe($this->combination->lensTechnology->name)
        ->and($lensItem->lensConfig->material_name)->toBe($this->combination->lensMaterial->name)
        ->and($lensItem->lensConfig->installation_price)->toBe($this->combination->installation_price)
        ->and($lensItem->lensConfig->package_name)->toBe($this->package->name)
        ->and($lensItem->lensConfig->treatments->pluck('name')->all())->toBe([$this->treatment->name]);
});

it('lets a seller-entered price_override win over the computed catalog price', function () {
    $sale = app(RegisterSale::class)->handle([
        'customer_id' => $this->customer->id,
        'document_type' => 'order',
        'armados' => [['lens' => armadoLens(['price_override' => 80000])]],
    ], $this->seller);

    $lensItem = $sale->items->first(fn ($i) => $i->isLens());

    expect($lensItem->unit_price)->toBe(80000)
        // cost tracking still reflects the real resolved catalog cost, override or not.
        ->and($lensItem->unit_cost)->toBe(40000);
});

it('auto-creates a pending-assignment lens order for every lens line', function () {
    $sale = app(RegisterSale::class)->handle([
        'customer_id' => $this->customer->id,
        'document_type' => 'order',
        'armados' => [['lens' => armadoLens()]],
    ], $this->seller);

    $lensItem = $sale->items->first(fn ($i) => $i->isLens());

    expect($lensItem->lensOrder)->not->toBeNull()
        ->and($lensItem->lensOrder->supplier_id)->toBeNull()
        ->and($lensItem->lensOrder->lab_status)->toBe(LensOrderStatus::PendingAssignment);
});

it('rejects an armado lens whose selected combination does not exist in the catalog', function () {
    expect(fn () => app(RegisterSale::class)->handle([
        'customer_id' => $this->customer->id,
        'document_type' => 'order',
        'armados' => [['lens' => armadoLens(['lens_material_id' => $this->combination->lens_material_id + 999])]],
    ], $this->seller))->toThrow(ValidationException::class);
});

it('ignores a client-sent unit_price and prices the lens from the catalog', function () {
    $sale = app(RegisterSale::class)->handle([
        'customer_id' => $this->customer->id,
        'document_type' => 'order',
        // `unit_price`/`unit_cost` are no longer part of the lens contract — a client
        // sending them must not be able to swap out the server-computed price.
        'armados' => [['lens' => armadoLens(['unit_price' => 1, 'unit_cost' => 1])]],
    ], $this->seller);

    $lensItem = $sale->items->first(fn ($i) => $i->isLens());

    expect($lensItem->unit_price)->toBe(125000)
        ->and($lensItem->unit_cost)->toBe(40000);
});
