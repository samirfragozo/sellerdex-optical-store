<?php
// tests/Browser/PosArmadoPatientTest.php

use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\LensCombination;
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

it('sells one armado for the payer and one for another patient', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    $companyId = $seller->company_id;
    CashRegisterSession::factory()->for($seller)->create(['company_id' => $companyId]);
    PaymentMethod::factory()->create(['company_id' => $companyId, 'is_active' => true]);
    Supplier::factory()->laboratory()->create(['company_id' => $companyId]);
    ProductCategory::factory()->create(['key' => 'lens', 'name' => 'Lentes', 'company_id' => $companyId]);
    $type = LensType::factory()->create(['name' => 'Monofocal', 'company_id' => $companyId]);
    $technology = LensTechnology::factory()->create(['name' => 'Estándar', 'company_id' => $companyId]);
    $material = LensMaterial::factory()->create(['name' => 'CR-39', 'company_id' => $companyId]);
    LensCombination::factory()->create([
        'company_id' => $companyId,
        'lens_type_id' => $type->id,
        'lens_technology_id' => $technology->id,
        'lens_material_id' => $material->id,
    ]);

    $payer = Customer::factory()->create(['company_id' => $companyId, 'name' => 'Ana', 'last_name' => 'Gómez', 'id_number' => '99999999']);
    $son = Customer::factory()->create(['company_id' => $companyId, 'name' => 'Luis', 'last_name' => 'Gómez', 'id_number' => '88888888']);
    $payerRx = Prescription::factory()->create(['company_id' => $companyId, 'customer_id' => $payer->id]);
    $sonRx = Prescription::factory()->create(['company_id' => $companyId, 'customer_id' => $son->id]);

    $this->actingAs($seller);

    // The cart header of an earlier armado also names the lens, so target the
    // wizard's toggle buttons (aria-pressed) only.
    $finishArmado = fn ($page) => $page
        ->click('button[aria-pressed]:has-text("'.$type->name.'")')
        ->click('button[aria-pressed]:has-text("'.$technology->name.'")')
        ->click('button[aria-pressed]:has-text("'.$material->name.'")')
        ->click('button:has-text("Continuar a la montura")')
        ->click('text=El cliente trae su montura')
        ->click('button:has-text("Continuar al combo")')
        ->click('button:has-text("Guardar ítem")');

    $page = visit('/pos')
        ->fill('#customer_id', 'Ana')
        ->wait(1)
        ->click('text=Ana Gómez')
        // Armado 1: for the payer (the default).
        ->click('Lentes')
        ->click('Usar existente')
        ->select('#prescription_id', (string) $payerRx->id)
        ->click('Continuar al lente');
    $finishArmado($page);

    // Armado 2: for another patient.
    $page->click('Lentes')
        ->click('Otro paciente')
        ->fill('#patient_id', 'Luis')
        ->wait(1)
        ->click('text=Luis Gómez')
        ->click('Usar existente')
        ->select('#prescription_id', (string) $sonRx->id)
        ->click('Continuar al lente');
    $finishArmado($page);

    $page->assertSee('Paciente: Luis Gómez')
        // Reopening the second armado keeps its patient and prescription,
        // so the seller can walk straight through and save it again.
        ->click('button:has-text("Editar ítem") >> nth=1')
        ->assertButtonEnabled('Continuar al lente')
        ->click('Continuar al lente')
        ->click('button:has-text("Continuar a la montura")')
        ->click('button:has-text("Continuar al combo")')
        ->click('button:has-text("Guardar ítem")')
        ->click('text=Cobrar')
        ->click('button:has-text("Confirmar venta")')
        ->assertSee('creada exitosamente')
        ->assertNoJavaScriptErrors();

    $configs = Sale::sole()->lensConfigs()->orderBy('sale_item_lens_configs.id')->get();
    expect($configs->pluck('patient_id')->all())->toBe([$payer->id, $son->id])
        ->and($configs->pluck('prescription_id')->all())->toBe([$payerRx->id, $sonRx->id]);
});
