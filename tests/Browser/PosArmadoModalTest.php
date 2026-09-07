<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ProductCategorySeeder;

it('lets a seller add, edit, and remove an armado from the pos cart', function () {
    $company = Company::factory()->create();
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);
    ProductCategory::withoutGlobalScopes()->whereNull('company_id')->update(['company_id' => $company->id]);
    Product::withoutGlobalScopes()->whereNull('company_id')->update(['company_id' => $company->id]);

    $this->actingAs(User::factory()->forCompany($company)->seller()->create());

    $editArmado = '[title="'.__('app.pos.edit_armado').'"]';
    $removeArmado = '[title="'.__('app.pos.remove_armado').'"]';

    $page = visit('/pos');

    $page->assertSee(__('app.pos.add_armado'));

    // Cancelling a new armado must not leave anything in the cart.
    $page->click(__('app.pos.add_armado'))
        ->assertSee(__('app.pos.steps.prescription'))
        ->press('Close')
        ->assertNotPresent($editArmado);

    // Walk the wizard for a real catalog lens (ML-052).
    $page->click(__('app.pos.add_armado'))
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

    $page->assertPresent($editArmado)
        ->assertSee('Lente Progresivo Terminado Material 1.56 Sin Filtro');

    // Editing reopens the wizard with the previous selection intact.
    $page->click($editArmado)
        ->assertSee(__('app.pos.steps.prescription'))
        ->press(__('app.pos.continue_to_lens'))
        ->assertSee('Lente Progresivo Terminado Material 1.56 Sin Filtro')
        ->press('Close');

    // Deleting removes the row immediately — this was the original bug.
    $page->click($removeArmado)
        ->assertNotPresent($editArmado);

    $page->assertNoJavaScriptErrors();
});
