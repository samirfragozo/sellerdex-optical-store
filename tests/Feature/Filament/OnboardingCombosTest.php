<?php

use App\Actions\SeedCompanyDefaults;
use App\Enums\ArmadoFramePriceMode;
use App\Enums\KitTrigger;
use App\Filament\Pages\ComboSettings;
use App\Filament\Pages\Onboarding;
use App\Filament\Pages\Onboarding\Steps\CombosStep;
use App\Models\Company;
use App\Models\KitSlot;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\ReferenceCounterCatalog;
use App\Support\ReferenceKit;
use Livewire\Livewire;

function combosAdmin(): User
{
    $company = Company::factory()->notOnboarded()->create(['onboarding_step' => CombosStep::key()]);
    (new SeedCompanyDefaults)->handle($company);
    $admin = User::factory()->forCompany($company)->admin()->create();
    test()->actingAs($admin);
    // Create the reference counter products the same way the counter-products step would.
    foreach (ReferenceCounterCatalog::categories() as $category) {
        $model = ProductCategory::firstOrCreate(['key' => $category['key']], ['name' => $category['name'], 'is_active' => true]);
        foreach ($category['products'] as $product) {
            Product::create([...$product, 'product_category_id' => $model->id, 'is_active' => true, 'is_pos_selectable' => true]);
        }
    }

    return $admin;
}

it('suggests the reference combo and saves the frame pricing', function () {
    $admin = combosAdmin();

    Livewire::test(Onboarding::class)
        ->assertSet('step', CombosStep::key())
        ->set('data.armado_frame_price_mode', ArmadoFramePriceMode::DiscountPercent->value)
        ->set('data.armado_frame_discount_percent', 20)
        ->call('next')
        ->assertHasNoErrors();

    expect(KitSlot::where('trigger', KitTrigger::Armado)->count())->toBe(4)
        ->and($admin->company->fresh()->armado_frame_price_mode)->toBe(ArmadoFramePriceMode::DiscountPercent);
});

it('refuses two slots of the same category in one combo', function () {
    combosAdmin();
    $page = Livewire::test(Onboarding::class);
    $slots = $page->get('data.armadoSlots');
    $first = array_key_first($slots);
    $second = array_keys($slots)[1];
    $slots[$second]['slot_category_id'] = $slots[$first]['slot_category_id'];

    $page->set('data.armadoSlots', $slots)->call('next')->assertHasErrors();
});

it('offers the same combos in the settings page', function () {
    $admin = combosAdmin();
    $admin->company->update(['onboarded_at' => now()]);
    ReferenceKit::installFor($admin->company);

    Livewire::test(ComboSettings::class)->assertSuccessful()->assertSee(__('app.onboarding.combos.label'));
});

it('previews the armado combo as the POS will show it', function () {
    combosAdmin();

    Livewire::test(Onboarding::class)
        ->assertSee('☑ Estuches: Estuche pequeño — '.__('app.pos.kit.gift'))
        ->assertSee('☐ Servicios: Examen visual — ')
        ->assertSee('$20.000 al lente');
});

it('saves the lens treatments step through the company relationship', function () {
    $admin = combosAdmin();
    $admin->company->update(['onboarding_step' => 'treatments']);

    Livewire::test(Onboarding::class)
        ->set('data.lensTreatments', [['name' => 'Antirreflejo', 'price' => 40_000, 'cost' => 10_000, 'is_active' => true]])
        ->call('next')
        ->assertHasNoErrors()
        ->assertSet('step', CombosStep::key());

    expect($admin->company->lensTreatments()->pluck('name')->all())->toBe(['Antirreflejo']);
});

it('refuses the same category twice for one product in the settings page', function () {
    $admin = combosAdmin();
    $admin->company->update(['onboarded_at' => now()]);
    $product = Product::where('name', 'Estuche grande')->sole();
    $cloth = ProductCategory::keyed('cloth');
    $row = ['trigger_product_id' => $product->id, 'slot_category_id' => $cloth->id, 'default_product_id' => $cloth->products()->value('id'), 'price_mode' => 'free', 'is_optional' => false];

    Livewire::test(ComboSettings::class)
        ->set('data.productSlots', ['a' => $row, 'b' => $row])
        ->call('save')
        ->assertHasErrors('data.productSlots');

    Livewire::test(ComboSettings::class)
        ->set('data.productSlots', ['a' => $row])
        ->call('save')
        ->assertHasNoErrors();

    expect(KitSlot::where('trigger', KitTrigger::Product)->sole()->scope_key)->toBe('product:'.$product->id);
});
