<?php

use App\Actions\RegisterSale;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;

require_once __DIR__.'/../Support/GoldenCatalog.php';

beforeEach(function () {
    $this->seller = User::factory()->seller()->create();
    $this->catalog = goldenCatalog($this->seller);
    $this->customer = Customer::factory()->create();
});

/** @return array<string, mixed> */
function goldenLens(array $catalog): array
{
    return [
        'description' => 'Lente formulado',
        'lens_type_id' => $catalog['lens']->lens_type_id,
        'lens_technology_id' => $catalog['lens']->lens_technology_id,
        'lens_material_id' => $catalog['lens']->lens_material_id,
        'lens_package_id' => $catalog['package']->id,
        'treatment_ids' => [],
    ];
}

function goldenRegister(array $payload): Sale
{
    return app(RegisterSale::class)->handle($payload, test()->seller);
}

it('S1: armado with frame, exam, large case, cloth and liquid', function () {
    $sale = goldenRegister([
        'customer_id' => $this->customer->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => goldenLens($this->catalog),
            'frame' => ['product_id' => $this->catalog['frame']->id, 'description' => 'Montura Golden', 'unit_price' => 150_000],
            'combo' => ['with_exam' => true, 'estuche' => 'large', 'include_pano' => true, 'include_liquid' => true],
        ]],
    ]);

    expect(goldenLines($sale))->toBe([
        ['group' => 'g1', 'sku' => null, 'description' => 'Lente formulado', 'qty' => 1, 'price' => 200_000],
        ['group' => 'g1', 'sku' => 'FRAME', 'description' => 'Montura Golden', 'qty' => 1, 'price' => 0],
        ['group' => 'g1', 'sku' => 'SRV-EXAMEN', 'description' => 'Examen visual', 'qty' => 1, 'price' => 0],
        ['group' => 'g1', 'sku' => 'ACC-ESTUCHE-LARGE', 'description' => 'Estuche grande', 'qty' => 1, 'price' => 0],
        ['group' => 'g1', 'sku' => 'ACC-PANO', 'description' => 'Paño', 'qty' => 1, 'price' => 0],
        ['group' => 'g1', 'sku' => 'ACC-LIQUIDO', 'description' => 'Líquido limpiador', 'qty' => 1, 'price' => 0],
        ['group' => null, 'sku' => 'ACC-BOLSA-PLASTICO', 'description' => 'Bolsa plástica', 'qty' => 1, 'price' => 0],
    ])->and($sale->total)->toBe(200_000);
});

it('S2: armado with own frame and the default combo', function () {
    $sale = goldenRegister([
        'customer_id' => $this->customer->id,
        'document_type' => 'order',
        'armados' => [['lens' => goldenLens($this->catalog), 'own_frame' => true]],
    ]);

    expect(goldenLines($sale))->toBe([
        ['group' => 'g1', 'sku' => null, 'description' => 'Lente formulado', 'qty' => 1, 'price' => 180_000],
        ['group' => 'g1', 'sku' => 'ACC-ESTUCHE-SMALL', 'description' => 'Estuche pequeño', 'qty' => 1, 'price' => 0],
        ['group' => 'g1', 'sku' => 'ACC-PANO', 'description' => 'Paño', 'qty' => 1, 'price' => 0],
        ['group' => null, 'sku' => 'ACC-BOLSA-PLASTICO', 'description' => 'Bolsa plástica', 'qty' => 1, 'price' => 0],
    ])->and($sale->total)->toBe(180_000);
});

it('S3: a frame sold alone gets a pouch and the plastic bag', function () {
    $frame = $this->catalog['frame'];
    $sale = goldenRegister([
        'document_type' => 'order',
        'products' => [['product_id' => $frame->id, 'description' => $frame->name, 'quantity' => 1, 'unit_price' => 150_000]],
    ]);

    expect(goldenLines($sale))->toBe([
        ['group' => null, 'sku' => 'FRAME', 'description' => 'Montura Golden', 'qty' => 1, 'price' => 150_000],
        ['group' => null, 'sku' => 'ACC-FUNDA', 'description' => 'Funda', 'qty' => 1, 'price' => 0],
        ['group' => null, 'sku' => 'ACC-BOLSA-PLASTICO', 'description' => 'Bolsa plástica', 'qty' => 1, 'price' => 0],
    ])->and($sale->total)->toBe(150_000);
});

it('S4: sunglasses over the bag threshold get the paper bag and no pouch', function () {
    $sunglasses = $this->catalog['sunglasses'];
    $sale = goldenRegister([
        'document_type' => 'order',
        'products' => [['product_id' => $sunglasses->id, 'description' => $sunglasses->name, 'quantity' => 1, 'unit_price' => 250_000]],
    ]);

    expect(goldenLines($sale))->toBe([
        ['group' => null, 'sku' => 'SUNGLASSES', 'description' => 'Gafas de sol Golden', 'qty' => 1, 'price' => 250_000],
        ['group' => null, 'sku' => 'ACC-BOLSA-PAPEL', 'description' => 'Bolsa de papel', 'qty' => 1, 'price' => 0],
    ])->and($sale->total)->toBe(250_000);
});

it('S5: split payment with a surcharged method', function () {
    $frame = $this->catalog['frame'];
    $cash = PaymentMethod::factory()->create(['surcharge_percent' => 0, 'is_active' => true]);
    $addi = PaymentMethod::factory()->create(['surcharge_percent' => 7, 'is_active' => true]);

    $sale = goldenRegister([
        'document_type' => 'order',
        'products' => [['product_id' => $frame->id, 'description' => $frame->name, 'quantity' => 1, 'unit_price' => 150_000]],
        'payments' => [
            ['payment_method_id' => $cash->id, 'amount' => 50_000],
            ['payment_method_id' => $addi->id, 'amount' => 100_000],
        ],
    ]);

    // Sale::surcharge_percent is a decimal(5,2) column: the weighted 4.6666...7%
    // rounds to 4.67 before recalculateTotals() applies it, giving 157_005 (not
    // the unrounded 157_000 one would get from 150_000 * 1.0466666...7).
    expect(count(goldenLines($sale)))->toBe(3)
        ->and($sale->total)->toBe(157_005)
        ->and($sale->totalPaid())->toBe(150_000);
});

it('S6: layaway armado keeps the same lines', function () {
    $sale = goldenRegister([
        'customer_id' => $this->customer->id,
        'document_type' => 'layaway',
        'armados' => [['lens' => goldenLens($this->catalog), 'own_frame' => true]],
    ]);

    expect(array_column(goldenLines($sale), 'sku'))->toBe([null, 'ACC-ESTUCHE-SMALL', 'ACC-PANO', 'ACC-BOLSA-PLASTICO'])
        ->and($sale->total)->toBe(180_000);
});

it('S7: armado combo without cloth or liquid omits the paño line', function () {
    $sale = goldenRegister([
        'customer_id' => $this->customer->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => goldenLens($this->catalog),
            'own_frame' => true,
            'combo' => ['with_exam' => false, 'estuche' => 'small', 'include_pano' => false, 'include_liquid' => false],
        ]],
    ]);

    expect(goldenLines($sale))->toBe([
        ['group' => 'g1', 'sku' => null, 'description' => 'Lente formulado', 'qty' => 1, 'price' => 180_000],
        ['group' => 'g1', 'sku' => 'ACC-ESTUCHE-SMALL', 'description' => 'Estuche pequeño', 'qty' => 1, 'price' => 0],
        ['group' => null, 'sku' => 'ACC-BOLSA-PLASTICO', 'description' => 'Bolsa plástica', 'qty' => 1, 'price' => 0],
    ])->and($sale->total)->toBe(180_000);
});
