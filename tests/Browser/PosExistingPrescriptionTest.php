<?php

use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\LensCombination;
use App\Models\LensMaterial;
use App\Models\LensTechnology;
use App\Models\LensType;
use App\Models\PaymentMethod;
use App\Models\Prescription;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('offers a prescription created earlier in the session as an existing option', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    CashRegisterSession::factory()->for($seller)->create(['company_id' => $seller->company_id]);
    PaymentMethod::factory()->create(['company_id' => $seller->company_id, 'is_active' => true]);
    Supplier::factory()->laboratory()->create(['company_id' => $seller->company_id]);

    // The "Lentes" catalog chip only renders when a `lens`-keyed category exists —
    // the lens sale itself now runs through the lens catalog wizard, not a product.
    ProductCategory::factory()->create(['key' => 'lens', 'name' => 'Lentes', 'company_id' => $seller->company_id]);

    $type = LensType::factory()->create(['name' => 'Monofocal', 'company_id' => $seller->company_id]);
    $technology = LensTechnology::factory()->create(['name' => 'Estándar', 'company_id' => $seller->company_id]);
    $material = LensMaterial::factory()->create(['name' => 'CR-39', 'company_id' => $seller->company_id]);
    LensCombination::factory()->create([
        'lens_type_id' => $type->id,
        'lens_technology_id' => $technology->id,
        'lens_material_id' => $material->id,
        'company_id' => $seller->company_id,
    ]);

    $customer = Customer::factory()->create(['company_id' => $seller->company_id, 'name' => 'Ana', 'last_name' => 'Gómez', 'id_number' => '99999999']);

    $this->actingAs($seller);

    $page = visit('/pos');
    $page->fill('#customer_id', 'Ana')
        ->wait(1)
        ->click('text=Ana Gómez')
        ->click('Lentes')
        ->assertButtonDisabled('Usar existente')
        ->fill('#rx_exam_date', now()->toDateString())
        ->fill('#rx_prescriber_name', 'Dra. Ana Gómez')
        ->click('Guardar fórmula')
        ->wait(1)
        ->assertSee('Fórmula guardada')
        ->click('Continuar al lente')
        ->click('button:has-text("'.$type->name.'")')
        ->click('button:has-text("'.$technology->name.'")')
        ->click('button:has-text("'.$material->name.'")')
        ->click('button:has-text("Continuar a la montura")')
        ->click('text=El cliente trae su montura')
        ->click('button:has-text("Continuar al combo")')
        ->click('button:has-text("Guardar ítem")')
        ->click('text=Cobrar')
        ->click('button:has-text("Confirmar venta")')
        ->assertSee('creada exitosamente')
        ->assertNoJavaScriptErrors();

    // Start a new sale for the same customer — the prescription just
    // created must now be selectable, without a full page reload.
    $page->fill('#customer_id', 'Ana')
        ->wait(1)
        ->click('text=Ana Gómez')
        ->click('Lentes')
        ->assertButtonEnabled('Usar existente')
        ->click('Usar existente');

    $optionCount = $page->script(
        "document.querySelectorAll('#prescription_id option').length"
    );

    expect($optionCount)->toBe(2);
});

it('saves the diopter values typed into the POS new-prescription form', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    CashRegisterSession::factory()->for($seller)->create(['company_id' => $seller->company_id]);
    PaymentMethod::factory()->create(['company_id' => $seller->company_id, 'is_active' => true]);
    Supplier::factory()->laboratory()->create(['company_id' => $seller->company_id]);
    ProductCategory::factory()->create(['key' => 'lens', 'name' => 'Lentes', 'company_id' => $seller->company_id]);
    // A priced, active lens combination — otherwise the "lens_price" readiness
    // blocker opens the blocking dialog instead of the armado wizard.
    LensCombination::factory()->create([
        'company_id' => $seller->company_id,
        'lens_type_id' => LensType::factory()->create(['company_id' => $seller->company_id])->id,
        'lens_technology_id' => LensTechnology::factory()->create(['company_id' => $seller->company_id])->id,
        'lens_material_id' => LensMaterial::factory()->create(['company_id' => $seller->company_id])->id,
    ]);

    $customer = Customer::factory()->create(['company_id' => $seller->company_id, 'name' => 'Ana', 'last_name' => 'Gómez', 'id_number' => '99999999']);

    $this->actingAs($seller);

    visit('/pos')
        ->fill('#customer_id', 'Ana')
        ->wait(1)
        ->click('text=Ana Gómez')
        ->click('Lentes')
        ->fill('#rx_exam_date', now()->toDateString())
        ->fill('#rx_prescriber_name', 'Dra. Ana Gómez')
        // Regression: typing into a DiopterInput used to throw inside emitValue()
        // (magnitude was cast to a number by Vue's v-model on <input type="number">),
        // silently dropping every diopter field from the saved prescription.
        ->fill('[aria-label="OD — Esfera"]', '1.25')
        ->fill('[aria-label="OD — ADD"]', '1')
        ->click('Guardar fórmula')
        ->wait(1)
        ->assertSee('Fórmula guardada')
        ->assertNoJavaScriptErrors();

    $prescription = Prescription::where('customer_id', $customer->id)->sole();

    expect($prescription->od_sphere)->toBe('1.25')
        ->and($prescription->od_add)->toBe('1.00');
});

it('shows a warning when the seller selects an expired prescription', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    CashRegisterSession::factory()->for($seller)->create(['company_id' => $seller->company_id]);
    PaymentMethod::factory()->create(['company_id' => $seller->company_id, 'is_active' => true]);
    Supplier::factory()->laboratory()->create(['company_id' => $seller->company_id]);
    ProductCategory::factory()->create(['key' => 'lens', 'name' => 'Lentes', 'company_id' => $seller->company_id]);
    // A priced, active lens combination — otherwise the "lens_price" readiness
    // blocker opens the blocking dialog instead of the armado wizard.
    LensCombination::factory()->create([
        'company_id' => $seller->company_id,
        'lens_type_id' => LensType::factory()->create(['company_id' => $seller->company_id])->id,
        'lens_technology_id' => LensTechnology::factory()->create(['company_id' => $seller->company_id])->id,
        'lens_material_id' => LensMaterial::factory()->create(['company_id' => $seller->company_id])->id,
    ]);

    $customer = Customer::factory()->create(['company_id' => $seller->company_id, 'name' => 'Ana', 'last_name' => 'Gómez', 'id_number' => '99999999']);
    $expired = Prescription::factory()->create([
        'company_id' => $seller->company_id,
        'customer_id' => $customer->id,
        'exam_date' => now()->subMonths(14)->toDateString(),
    ]);

    $this->actingAs($seller);

    visit('/pos')
        ->fill('#customer_id', 'Ana')
        ->wait(1)
        ->click('text=Ana Gómez')
        ->click('Lentes')
        ->click('Usar existente')
        ->select('#prescription_id', (string) $expired->id)
        ->assertSee('Esta fórmula está vencida')
        ->assertNoJavaScriptErrors();
});
