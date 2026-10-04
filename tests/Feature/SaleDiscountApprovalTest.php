<?php

use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\LensCombination;
use App\Models\LensTreatment;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;

beforeEach(function () {
    $this->seller = User::factory()->seller()->create();
    $this->company = $this->seller->company;
    $this->admin = User::factory()->admin()->create(['company_id' => $this->company->id, 'approval_pin' => '4321']);
    $this->actingAs($this->seller);
    openCashRegisterSession($this->seller);
    $this->product = Product::factory()->create(['price' => 50_000]);
});

/**
 * @param  array<string,mixed>  $overrides
 * @return array<string,mixed>
 */
function discountApprovalPayload(Product $product, array $overrides = []): array
{
    return [
        'document_type' => 'order',
        'products' => [['product_id' => $product->id, 'description' => $product->name, 'quantity' => 1, 'unit_price' => $product->price]],
        ...$overrides,
    ];
}

it('asks for an admin pin when a seller discounts above the cap', function () {
    $this->company->update(['seller_max_discount_percent' => 5]);

    $this->postJson(route('pos.store'), discountApprovalPayload($this->product, ['discount_percent' => 10]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['approval_pin' => __('app.approval.required')]);

    expect(Sale::count())->toBe(0);
});

it('records the approving admin when the pin is right', function () {
    $this->company->update(['seller_max_discount_percent' => 5]);

    $this->postJson(route('pos.store'), discountApprovalPayload($this->product, ['discount_percent' => 10, 'approval_pin' => '4321']))
        ->assertOk();

    expect(Sale::sole()->discount_approved_by)->toBe($this->admin->id);
});

it('rejects a wrong pin', function () {
    $this->company->update(['seller_max_discount_percent' => 5]);

    $this->postJson(route('pos.store'), discountApprovalPayload($this->product, ['discount_percent' => 10, 'approval_pin' => '0000']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['approval_pin' => __('app.approval.invalid')]);

    expect(Sale::count())->toBe(0);
});

it('lets a seller discount up to the cap without approval', function () {
    $this->company->update(['seller_max_discount_percent' => 10]);

    $this->postJson(route('pos.store'), discountApprovalPayload($this->product, ['discount_percent' => 10]))->assertOk();

    expect(Sale::sole()->discount_approved_by)->toBeNull();
});

it('asks for approval when a catalog price is lowered but not when it is raised', function () {
    $lowered = discountApprovalPayload($this->product);
    $lowered['products'][0]['unit_price'] = 49_999;
    $this->postJson(route('pos.store'), $lowered)->assertStatus(422)->assertJsonValidationErrors('approval_pin');

    $raised = discountApprovalPayload($this->product);
    $raised['products'][0]['unit_price'] = 60_000;
    $this->postJson(route('pos.store'), $raised)->assertOk();
});

it('lets an admin discount anything and records them as approver', function () {
    $this->actingAs($this->admin);
    CashRegisterSession::factory()->for($this->admin)->create(['company_id' => $this->company->id]);

    $this->postJson(route('pos.store'), discountApprovalPayload($this->product, ['discount_percent' => 40]))->assertOk();

    expect(Sale::sole()->discount_approved_by)->toBe($this->admin->id);
});

it('asks for approval when a lens price_override is below the resolved price but not above it', function () {
    $customer = Customer::factory()->create();
    $prescription = Prescription::factory()->create(['customer_id' => $customer->id]);
    $combination = LensCombination::factory()->priced(20_000, 10_000)->create(['installation_price' => 5_000]);
    $treatment = LensTreatment::factory()->create(['price' => 70_000, 'cost' => 20_000]);
    Supplier::factory()->laboratory()->create(['company_id' => $this->company->id]);

    // Resolved lens price: 20 000 + 5 000 installation + 70 000 treatment = 95 000.
    $payload = fn (int $override): array => [
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'armados' => [[
            'prescription_id' => $prescription->id,
            'own_frame' => true,
            'lens' => [
                'description' => 'Lente formulado', 'quantity' => 1, 'price_override' => $override,
                'lens_type_id' => $combination->lens_type_id,
                'lens_technology_id' => $combination->lens_technology_id,
                'lens_material_id' => $combination->lens_material_id,
                'treatment_ids' => [$treatment->id],
            ],
        ]],
    ];

    $this->postJson(route('pos.store'), $payload(94_999))->assertStatus(422)->assertJsonValidationErrors('approval_pin');
    expect(Sale::count())->toBe(0);

    $this->postJson(route('pos.store'), $payload(95_001))->assertOk();
});
