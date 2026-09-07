<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ProductCategorySeeder;

it('lets a seller add, edit, and remove an armado through the catalog lens wizard', function () {
    $company = Company::factory()->create();
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);
    ProductCategory::withoutGlobalScopes()->whereNull('company_id')->update(['company_id' => $company->id]);
    Product::withoutGlobalScopes()->whereNull('company_id')->update(['company_id' => $company->id]);

    $lens = Product::where('sku', 'ML-052')->first();

    $seller = User::factory()->forCompany($company)->seller()->create();
    $this->actingAs($seller);
    openCashRegisterSession($seller, 100_000);

    $editArmado = __('app.pos.edit_armado');
    $removeArmado = __('app.pos.remove_armado');

    $page = visit('/pos');

    // Clicking a lens catalog card opens the armado wizard on its
    // prescription step, instead of adding a loose cart line.
    $page->click($lens->name)
        ->assertSee(__('app.pos.steps.prescription'));

    // Cancelling a new armado must not leave anything in the cart.
    $page->click('Close')
        ->assertDontSee($editArmado);

    // Walk the wizard for the real catalog lens (ML-052).
    $page->click($lens->name)
        ->assertSee(__('app.pos.steps.prescription'))
        ->press(__('app.pos.continue_to_lens'))
        ->click('Progresivo')
        ->click('Terminado')
        ->click('Material 1.56')
        ->click('Sin Filtro')
        ->assertSee('Lente Progresivo Terminado Material 1.56 Sin Filtro')
        ->press(__('app.pos.continue_to_frame'))
        ->click(__('app.pos.frame_form.own_frame_toggle'))
        ->press(__('app.pos.continue_to_combo'))
        ->press(__('app.pos.save_armado'));

    $page->assertSee($editArmado)
        ->assertSee('Lente Progresivo Terminado Material 1.56 Sin Filtro');

    // Editing reopens the wizard with the previous selection intact.
    $page->click($editArmado)
        ->assertSee(__('app.pos.steps.prescription'))
        ->press(__('app.pos.continue_to_lens'))
        ->assertSee('Lente Progresivo Terminado Material 1.56 Sin Filtro')
        ->click('Close');

    // Deleting removes the row immediately — this was the original bug.
    $page->click($removeArmado)
        ->assertDontSee($editArmado);

    $page->assertNoJavaScriptErrors();
});
