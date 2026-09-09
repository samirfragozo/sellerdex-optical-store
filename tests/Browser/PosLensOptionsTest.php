<?php

use App\Models\Company;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ProductCategorySeeder;

it('lets a seller sell a lens priced from its option groups', function () {
    $company = Company::factory()->create();
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);
    ProductCategory::withoutGlobalScopes()->whereNull('company_id')->update(['company_id' => $company->id]);
    Product::withoutGlobalScopes()->whereNull('company_id')->update(['company_id' => $company->id]);
    OptionGroup::withoutGlobalScopes()->whereNull('company_id')->update(['company_id' => $company->id]);

    $lens = Product::where('sku', 'LOPT-MONOFOCAL')->first();

    $seller = User::factory()->forCompany($company)->seller()->create();
    $this->actingAs($seller);
    openCashRegisterSession($seller, 100_000);

    // The demo product's name contains parentheses, which the browser
    // testing driver's `click()` would otherwise misparse as CSS syntax —
    // use explicit Playwright selector engines instead. The catalog card
    // (a <span>) and the "pick lens" chip (a <button>) both carry the same
    // text once the wizard is open, so the chip needs a role-scoped selector
    // to avoid a strict-mode ambiguity between the two.
    $lensCardSelector = 'text='.$lens->name;
    $lensChipSelector = 'role=button[name="'.$lens->name.'"]';

    $page = visit('/pos');

    // Clicking the option-driven lens's catalog card opens the armado wizard
    // on its prescription step, same as any other lens.
    $page->click($lensCardSelector)
        ->assertSee(__('app.pos.steps.prescription'))
        ->press(__('app.pos.continue_to_lens'))
        ->assertSee(__('app.pos.lens_form.pick_lens'))
        ->click($lensChipSelector)
        ->assertSee('Material')
        ->click('Policarbonato')
        ->assertSee('Filtro')
        ->click('Blue Cut')
        ->assertSee($lens->name)
        ->assertSee('$100.000')
        ->press(__('app.pos.continue_to_frame'))
        ->click(__('app.pos.frame_form.own_frame_toggle'))
        ->press(__('app.pos.continue_to_combo'))
        ->press(__('app.pos.save_armado'));

    $page->assertSee(__('app.pos.edit_armado'))
        ->assertSee($lens->name)
        ->assertNoJavaScriptErrors();
});
