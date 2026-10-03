<?php

use App\Enums\FrameSource;
use App\Enums\FrameType;
use App\Enums\LensOrderStatus;
use App\Models\Customer;
use App\Models\LensCombination;
use App\Models\LensOrder;
use App\Models\PaymentMethod;
use App\Models\Prescription;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;

beforeEach(function () {
    $this->seller = User::factory()->seller()->create();
    $this->actingAs($this->seller);
    openCashRegisterSession($this->seller);
    PaymentMethod::factory()->create(['is_active' => true]);
    Supplier::factory()->laboratory()->create();
    $this->combination = LensCombination::factory()->create();
    $this->customer = Customer::factory()->create();
    $this->rx = Prescription::factory()->create([
        'customer_id' => $this->customer->id,
        'od_sphere' => '-2.25', 'od_cylinder' => '-0.50', 'od_axis' => 180, 'od_add' => null, 'od_pd' => '31.5',
        'os_sphere' => '-2.00', 'os_cylinder' => null, 'os_axis' => null, 'os_add' => null, 'os_pd' => '32.0',
    ]);
});

/** A one-armado POS sale payload on the test prescription, merged with $armado overrides. */
function labOrderSalePayload(array $armado = []): array
{
    $combination = test()->combination;

    return [
        'document_type' => 'order',
        'customer_id' => test()->customer->id,
        'payments' => [],
        'armados' => [[
            'prescription_id' => test()->rx->id,
            'lens' => [
                'description' => 'Lente', 'quantity' => 1, 'treatment_ids' => [],
                'lens_type_id' => $combination->lens_type_id,
                'lens_technology_id' => $combination->lens_technology_id,
                'lens_material_id' => $combination->lens_material_id,
            ],
            'own_frame' => true,
            ...$armado,
        ]],
    ];
}

it('fills the lab order with the prescription snapshot, PD and measurements', function () {
    $this->postJson(route('pos.store'), labOrderSalePayload([
        'measurements' => ['od_height' => 18, 'os_height' => 18.5, 'frame_a' => 52, 'frame_b' => 38, 'frame_dbl' => 18, 'frame_type' => 'full_rim'],
        'own_frame_description' => 'Montura metálica dorada',
        'own_frame_condition' => 'Rayón leve en la varilla derecha',
    ]))->assertOk();

    $order = LensOrder::sole();
    expect($order->lab_status)->toBe(LensOrderStatus::PendingAssignment)
        ->and($order->od_pd)->toBe('31.5')
        ->and($order->os_pd)->toBe('32.0')
        ->and($order->od_height)->toBe('18.0')
        ->and($order->os_height)->toBe('18.5')
        ->and($order->frame_a)->toBe('52.0')
        ->and($order->frame_type)->toBe(FrameType::FullRim)
        ->and($order->frame_source)->toBe(FrameSource::CustomerOwn)
        ->and($order->customer_frame_description)->toBe('Montura metálica dorada')
        ->and($order->customer_frame_condition)->toBe('Rayón leve en la varilla derecha')
        ->and($order->prescription_snapshot['od_sphere'])->toBe('-2.25')
        ->and($order->prescription_snapshot['od_axis'])->toBe(180)
        ->and($order->prescription_snapshot['os_cylinder'])->toBeNull();
});

it('keeps the snapshot when the prescription is edited after the sale', function () {
    $this->postJson(route('pos.store'), labOrderSalePayload())->assertOk();

    $this->rx->update(['od_sphere' => '-5.00']);

    expect(LensOrder::sole()->prescription_snapshot['od_sphere'])->toBe('-2.25');
});

it('marks a sold frame as sold and ignores own-frame details', function () {
    $this->postJson(route('pos.store'), labOrderSalePayload([
        'own_frame' => false,
        'frame' => ['description' => 'Montura X', 'unit_price' => 100_000],
        'own_frame_description' => 'should be ignored',
    ]))->assertOk();

    $order = LensOrder::sole();
    expect($order->frame_source)->toBe(FrameSource::Sold)
        ->and($order->customer_frame_description)->toBeNull();
});

it('rejects out-of-range measurements and an unknown frame type', function () {
    $this->postJson(route('pos.store'), labOrderSalePayload([
        'measurements' => ['od_height' => 80, 'frame_type' => 'wire'],
    ]))->assertStatus(422)->assertJsonValidationErrors(['armados.0.measurements.od_height', 'armados.0.measurements.frame_type']);
});

it('only lets a sale be delivered when every lens order is ready', function () {
    $this->postJson(route('pos.store'), labOrderSalePayload())->assertOk();
    $sale = Sale::sole();
    $order = LensOrder::sole();

    $order->update(['lab_status' => LensOrderStatus::Received]);
    expect($sale->fresh()->canBeDelivered())->toBeFalse();

    $order->update(['lab_status' => LensOrderStatus::Ready]);
    expect($sale->fresh()->canBeDelivered())->toBeTrue();
});

it('judges deliverability by the latest order of a lens item', function () {
    $this->postJson(route('pos.store'), labOrderSalePayload())->assertOk();
    $original = LensOrder::sole();
    $original->update(['lab_status' => LensOrderStatus::Ready]);
    $remake = LensOrder::factory()->create([
        'sale_item_id' => $original->sale_item_id, 'remake_of_id' => $original->id,
        'lab_status' => LensOrderStatus::PendingAssignment,
    ]);

    expect(Sale::sole()->canBeDelivered())->toBeFalse()
        ->and(LensOrder::pending()->pluck('id')->all())->toBe([$remake->id]);
});
