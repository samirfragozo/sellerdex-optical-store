<?php

use App\Models\Customer;
use App\Models\KitSlot;
use App\Models\PaymentMethod;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ReferenceKit;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/../Support/GoldenCatalog.php';

uses(RefreshDatabase::class);

it('lets the seller pick combo slot products for an armado', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    $golden = goldenCatalog($seller);
    ReferenceKit::installFor($seller->company);
    openCashRegisterSession($seller);
    PaymentMethod::factory()->create(['company_id' => $seller->company_id, 'is_active' => true]);
    Supplier::factory()->laboratory()->create(['company_id' => $seller->company_id]);
    ProductCategory::factory()->create(['key' => 'lens', 'name' => 'Lentes', 'company_id' => $seller->company_id]);
    Customer::factory()->create(['company_id' => $seller->company_id, 'name' => 'Ana', 'last_name' => 'Gómez', 'id_number' => '99999999']);
    $lens = $golden['lens'];
    $slotId = fn (string $key): int => KitSlot::query()->whereHas('slotCategory', fn ($q) => $q->where('key', $key))->value('id');

    visit('/pos')
        ->fill('#customer_id', 'Ana')
        ->wait(1)
        ->click('text=Ana Gómez')
        ->click('Lentes')
        ->fill('#rx_exam_date', now()->toDateString())
        ->click('Continuar al lente')
        ->click('button:has-text("'.$lens->lensType->name.'")')
        ->click('button:has-text("'.$lens->lensTechnology->name.'")')
        ->click('button:has-text("'.$lens->lensMaterial->name.'")')
        ->click('button:has-text("Continuar a la montura")')
        ->click('text=El cliente trae su montura')
        ->click('button:has-text("Continuar al combo")')
        ->select('#kit_slot_'.$slotId('case'), 'Estuche grande')
        ->check('#kit_slot_'.$slotId('service').'_selected')
        ->click('button:has-text("Guardar ítem")')
        ->assertSee('$200.000')
        ->click('text=Cobrar')
        ->click('button:has-text("Confirmar venta")')
        ->assertSee('creada exitosamente')
        ->assertNoJavaScriptErrors();

    $sale = Sale::query()->latest('id')->firstOrFail();
    $skus = $sale->items()->with('product')->get()->pluck('product.sku');
    $lensLine = $sale->items()->whereHas('lensConfig')->firstOrFail();

    expect($skus)->toContain('ACC-ESTUCHE-LARGE')
        ->and((int) $lensLine->unit_price)->toBe(200_000)
        ->and((int) $sale->total)->toBe(200_000);
});

it('keeps an armado\'s slot choices when it is reopened and saved from the cart', function () {
    test()->seed(RolesAndPermissionsSeeder::class);
    $seller = User::factory()->seller()->create();
    $lens = goldenCatalog($seller)['lens'];
    ReferenceKit::installFor($seller->company);
    openCashRegisterSession($seller);
    PaymentMethod::factory()->create(['company_id' => $seller->company_id, 'is_active' => true]);
    Supplier::factory()->laboratory()->create(['company_id' => $seller->company_id]);
    ProductCategory::factory()->create(['key' => 'lens', 'name' => 'Lentes', 'company_id' => $seller->company_id]);
    Customer::factory()->create(['company_id' => $seller->company_id, 'name' => 'Ana', 'last_name' => 'Gómez', 'id_number' => '99999999']);
    $caseSlotId = KitSlot::query()->whereHas('slotCategory', fn ($q) => $q->where('key', 'case'))->value('id');

    $page = visit('/pos')
        ->fill('#customer_id', 'Ana')
        ->wait(1)
        ->click('text=Ana Gómez')
        ->click('Lentes')
        ->fill('#rx_exam_date', now()->toDateString());

    // Armado 1 takes the large case; armado 2 keeps the default (small) one.
    foreach (['Estuche grande', null] as $case) {
        if ($case === null) {
            $page->click('Lentes');
        }
        $page->click('Continuar al lente')
            ->click('button[aria-pressed]:has-text("'.$lens->lensType->name.'")')
            ->click('button[aria-pressed]:has-text("'.$lens->lensTechnology->name.'")')
            ->click('button[aria-pressed]:has-text("'.$lens->lensMaterial->name.'")')
            ->click('button:has-text("Continuar a la montura")')
            ->click('text=El cliente trae su montura')
            ->click('button:has-text("Continuar al combo")');
        if ($case !== null) {
            $page->select('#kit_slot_'.$caseSlotId, $case);
        }
        $page->click('button:has-text("Guardar ítem")');
    }

    // Reopen armado 1 and save it untouched: its large case must survive.
    $page->click('button:has-text("Editar ítem") >> nth=0')
        ->click('Continuar al lente')
        ->click('button:has-text("Continuar a la montura")')
        ->click('button:has-text("Continuar al combo")')
        ->click('button:has-text("Guardar ítem")')
        ->assertSee('$360.000')
        ->click('text=Cobrar')
        ->click('button:has-text("Confirmar venta")')
        ->assertSee('creada exitosamente')
        ->assertNoJavaScriptErrors();

    $sale = Sale::query()->latest('id')->firstOrFail();
    $cases = $sale->items()->with('product')->orderBy('group_key')->get()
        ->filter(fn ($item) => str_starts_with((string) $item->product?->sku, 'ACC-ESTUCHE'))
        ->mapWithKeys(fn ($item) => [$item->group_key => $item->product->sku])
        ->all();

    expect($cases)->toBe(['g1' => 'ACC-ESTUCHE-LARGE', 'g2' => 'ACC-ESTUCHE-SMALL'])
        ->and((int) $sale->total)->toBe(360_000);
});
