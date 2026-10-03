<?php

use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\LensCombination;
use App\Models\LensCombinationPrice;
use App\Models\LensMaterial;
use App\Models\LensTechnology;
use App\Models\LensType;
use App\Models\PaymentMethod;
use App\Models\Prescription;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('preselects the preferred lab, re-prices when the seller switches lab, and keeps it on re-edit', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    $companyId = $seller->company_id;
    CashRegisterSession::factory()->for($seller)->create(['company_id' => $companyId]);
    PaymentMethod::factory()->create(['company_id' => $companyId, 'is_active' => true]);
    ProductCategory::factory()->create(['key' => 'lens', 'name' => 'Lentes', 'company_id' => $companyId]);
    $labA = Supplier::factory()->laboratory()->create(['company_id' => $companyId, 'name' => 'Lab Central']);
    $labB = Supplier::factory()->laboratory()->create(['company_id' => $companyId, 'name' => 'Lab Norte']);
    $type = LensType::factory()->create(['name' => 'Monofocal', 'company_id' => $companyId]);
    $technology = LensTechnology::factory()->create(['name' => 'Estándar', 'company_id' => $companyId]);
    $material = LensMaterial::factory()->create(['name' => 'CR-39', 'company_id' => $companyId]);
    $otherMaterial = LensMaterial::factory()->create(['name' => 'Policarbonato', 'company_id' => $companyId]);
    // Same type and technology, two materials priced differently, so switching material must re-price.
    foreach ([
        [$material, [[$labA, 150_000, 50_000, true], [$labB, 170_000, 30_000, false]]],
        [$otherMaterial, [[$labA, 180_000, 55_000, true], [$labB, 200_000, 35_000, false]]],
    ] as [$lensMaterial, $offers]) {
        $combination = LensCombination::factory()->unpriced()->create([
            'company_id' => $companyId, 'installation_price' => 0,
            'lens_type_id' => $type->id, 'lens_technology_id' => $technology->id, 'lens_material_id' => $lensMaterial->id,
        ]);
        foreach ($offers as [$lab, $price, $cost, $preferred]) {
            LensCombinationPrice::factory()->create([
                'company_id' => $companyId, 'lens_combination_id' => $combination->id, 'supplier_id' => $lab->id,
                ...LensCombinationPrice::ALL_PRESCRIPTIONS, 'price' => $price, 'cost' => $cost, 'is_preferred' => $preferred,
            ]);
        }
    }
    $customer = Customer::factory()->create(['company_id' => $companyId, 'name' => 'Ana', 'last_name' => 'Gómez', 'id_number' => '99999999']);
    $rx = Prescription::factory()->create(['company_id' => $companyId, 'customer_id' => $customer->id]);

    $this->actingAs($seller);

    visit('/pos')
        ->fill('#customer_id', 'Ana')
        ->wait(1)
        ->click('text=Ana Gómez')
        ->click('Lentes')
        ->click('Usar existente')
        ->select('#prescription_id', (string) $rx->id)
        ->click('Continuar al lente')
        ->click('button[aria-pressed]:has-text("Monofocal")')
        ->click('button[aria-pressed]:has-text("Estándar")')
        ->click('button[aria-pressed]:has-text("CR-39")')
        ->assertSee('$150.000')
        ->assertAttribute('button[aria-pressed]:has-text("Lab Central")', 'aria-pressed', 'true')
        // Switching material re-prices instead of carrying the previous material's price.
        ->click('button[aria-pressed]:has-text("Policarbonato")')
        ->assertSee('$180.000')
        ->assertAttribute('button[aria-pressed]:has-text("Lab Central")', 'aria-pressed', 'true')
        ->click('button[aria-pressed]:has-text("Lab Norte")')
        ->assertAttribute('button[aria-pressed]:has-text("Lab Norte")', 'aria-pressed', 'true')
        ->assertSee('$200.000')
        ->click('button:has-text("Continuar a la montura")')
        ->click('text=El cliente trae su montura')
        ->click('button:has-text("Continuar al combo")')
        ->click('button:has-text("Guardar ítem")')
        // Re-editing keeps the chosen lab instead of snapping back to the preferred one.
        ->click('button:has-text("Editar ítem")')
        ->click('Continuar al lente')
        ->assertAttribute('button[aria-pressed]:has-text("Lab Norte")', 'aria-pressed', 'true')
        ->assertSee('$200.000')
        ->click('button:has-text("Continuar a la montura")')
        ->click('button:has-text("Continuar al combo")')
        ->click('button:has-text("Guardar ítem")')
        ->click('text=Cobrar')
        ->click('button:has-text("Confirmar venta")')
        ->assertSee('creada exitosamente')
        ->assertNoJavaScriptErrors();

    $lens = Sale::sole()->items()->whereHas('lensConfig')->with('lensOrder')->sole();
    expect($lens->unit_price)->toBe(200_000)
        ->and($lens->unit_cost)->toBe(35_000)
        ->and($lens->lensOrder->supplier_id)->toBe($labB->id);
});
