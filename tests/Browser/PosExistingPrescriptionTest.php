<?php

use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('offers a prescription created earlier in the session as an existing option', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    CashRegisterSession::factory()->for($seller)->create(['company_id' => $seller->company_id]);

    $lensCategory = ProductCategory::factory()->create(['key' => 'lens', 'name' => 'Lentes', 'company_id' => $seller->company_id]);
    $lens = Product::factory()->create([
        'name' => 'Lente Monofocal',
        'product_category_id' => $lensCategory->id,
        'company_id' => $seller->company_id,
        'is_active' => true,
        'is_pos_selectable' => true,
        'price' => 50_000,
    ]);
    $group = OptionGroup::factory()->create(['company_id' => $seller->company_id, 'is_required' => true]);
    $option = Option::factory()->create(['option_group_id' => $group->id, 'name' => 'Basico', 'price' => 0]);
    $lens->optionGroups()->attach($group->id);

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
        ->click('button:has-text("Lente Monofocal")')
        ->click('button:has-text("'.$option->name.'")')
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
