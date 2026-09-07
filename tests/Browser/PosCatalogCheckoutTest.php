<?php

use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;

it('sells a loose product end to end through the new catalog and cart', function () {
    $seller = User::factory()->seller()->create();

    // Company-scoped models pick up the acting user's company_id at
    // creation time, so the seller must be authenticated first.
    $this->actingAs($seller);

    $category = ProductCategory::factory()->create(['key' => 'accessory', 'name' => 'Accesorio']);
    $product = Product::factory()->create([
        'product_category_id' => $category->id,
        'name' => 'Estuche rígido',
        'price' => 15_000,
        'is_pos_selectable' => true,
        'is_active' => true,
        'is_stockable' => false,
    ]);
    $paymentMethod = PaymentMethod::factory()->create(['name' => 'Efectivo']);

    $page = visit('/pos');

    // Cash session gate blocks the page until a session is opened.
    $page->assertSee(__('app.pos.cash_session.open_title'))
        ->type('#opening_cash', '100000')
        // The dialog title and the submit button share the exact same
        // translation string ("Abrir caja"), so a plain text click would
        // hit the (non-interactive) title. Target the button explicitly.
        ->click('[data-slot="dialog-content"] [data-slot="button"]')
        ->assertDontSee(__('app.pos.cash_session.open_title'));

    // The checkout button is disabled while the cart is empty...
    $page->assertButtonDisabled(__('app.pos.checkout.title'));

    // ...and becomes enabled the moment a catalog card is clicked into the cart.
    $page->click($product->name)
        ->assertButtonEnabled(__('app.pos.checkout.title'));

    // Checkout with a single cash payment.
    $page->click(__('app.pos.checkout.title'))
        ->click(__('app.pos.checkout.add_payment'))
        ->select('[data-slot="dialog-content"] select', (string) $paymentMethod->id)
        ->type('[data-slot="dialog-content"] input[type=number]', '15000')
        ->click(__('app.pos.checkout.confirm'))
        ->assertSee(__('app.documents.print_invoice'));

    $page->assertNoJavaScriptErrors();
});
