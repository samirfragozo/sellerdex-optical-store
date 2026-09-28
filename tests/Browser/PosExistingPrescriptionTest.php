<?php

use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\LensCombination;
use App\Models\LensMaterial;
use App\Models\LensTechnology;
use App\Models\LensType;
use App\Models\PaymentMethod;
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
