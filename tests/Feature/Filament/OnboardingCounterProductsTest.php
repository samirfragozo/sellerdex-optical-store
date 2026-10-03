<?php

use App\Actions\SeedCompanyDefaults;
use App\Filament\Pages\Onboarding;
use App\Filament\Pages\Onboarding\Steps\CounterProductsStep;
use App\Filament\Pages\Onboarding\Steps\LaboratoriesStep;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\ReferenceKit;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

function counterAdmin(): User
{
    $company = Company::factory()->notOnboarded()->create(['onboarding_step' => CounterProductsStep::key()]);
    (new SeedCompanyDefaults)->handle($company);
    $admin = User::factory()->forCompany($company)->admin()->create();
    test()->actingAs($admin);

    return $admin;
}

it('suggests the reference counter catalog and creates it', function () {
    counterAdmin();

    Livewire::test(Onboarding::class)
        ->assertSet('step', CounterProductsStep::key())
        ->call('next')
        ->assertHasNoErrors()
        ->assertSet('step', LaboratoriesStep::key());

    $case = Product::where('name', 'Estuche pequeño')->sole();
    expect($case->category->key)->toBe('case')
        ->and($case->only(['price', 'cost', 'is_active', 'is_pos_selectable', 'sku']))
        ->toBe(['price' => 10_000, 'cost' => 2_900, 'is_active' => true, 'is_pos_selectable' => true, 'sku' => null])
        ->and($case->tax_id)->toBe($case->category->default_tax_id)->not->toBeNull()
        ->and(Product::where('name', 'Examen visual')->sole()->category->key)->toBe('service')
        ->and(ProductCategory::where('key', 'service')->count())->toBe(1)
        ->and(ProductCategory::keyed('case')->is_system)->toBeFalse();
});

it('creates the service as not stockable and the physical products as stockable', function () {
    counterAdmin();

    Livewire::test(Onboarding::class)->call('next')->assertHasNoErrors();

    expect(Product::where('name', 'Examen visual')->sole()->is_stockable)->toBeFalse()
        ->and(Product::where('name', 'Estuche pequeño')->sole()->is_stockable)->toBeTrue();
});

it('renames and removes products when coming back instead of duplicating them', function () {
    counterAdmin();
    $page = Livewire::test(Onboarding::class)->call('next')->call('previous');
    $smallCaseId = Product::where('name', 'Estuche pequeño')->sole()->id;
    $counterProducts = Product::count();

    $state = $page->get('data.categories');
    $caseKey = collect($state)->search(fn ($c) => $c['key'] === 'case');
    $productKeys = array_keys($state[$caseKey]['products']);
    $state[$caseKey]['products'][$productKeys[0]]['name'] = 'Estuche de cuero';
    unset($state[$caseKey]['products'][$productKeys[1]]); // drop "Estuche grande"
    $page->set('data.categories', $state)->call('next')->assertHasNoErrors();

    expect(Product::where('name', 'Estuche de cuero')->sole()->id)->toBe($smallCaseId)
        ->and(Product::count())->toBe($counterProducts - 1)
        ->and(Product::where('name', 'Estuche pequeño')->exists())->toBeFalse()
        ->and(Product::where('name', 'Estuche grande')->exists())->toBeFalse();
});

it('creates a new category with a unique key and removes an unused one', function () {
    counterAdmin();
    ProductCategory::create(['name' => 'Otra', 'key' => 'lentes-de-contacto', 'is_active' => true]);
    $page = Livewire::test(Onboarding::class);

    $state = collect($page->get('data.categories'))
        ->reject(fn ($c) => $c['key'] === 'lentes-de-contacto')
        ->push(['category_id' => null, 'key' => null, 'name' => 'Lentes de contacto', 'products' => [
            ['product_id' => null, 'name' => 'Lente de contacto mensual', 'price' => 50_000, 'cost' => 20_000],
        ]])->all();
    $page->set('data.categories', $state)->call('next')->assertHasNoErrors();

    $category = Product::where('name', 'Lente de contacto mensual')->sole()->category;
    expect($category->name)->toBe('Lentes de contacto')
        ->and($category->key)->toBe('lentes-de-contacto-2')
        ->and($category->is_system)->toBeFalse()
        ->and(ProductCategory::where('name', 'Otra')->exists())->toBeFalse();
});

it('never deletes a category that a combo slot uses', function () {
    $admin = counterAdmin();
    $page = Livewire::test(Onboarding::class)->call('next');
    ReferenceKit::installFor($admin->company);
    $page->call('previous');

    $state = collect($page->get('data.categories'))->reject(fn ($c) => $c['key'] === 'case')->all();
    $page->set('data.categories', $state)->call('next')->assertHasNoErrors();

    expect(ProductCategory::keyed('case'))->not->toBeNull();
});

it('keeps system categories when they are removed from the list', function () {
    counterAdmin();
    $page = Livewire::test(Onboarding::class);

    $state = collect($page->get('data.categories'))->reject(fn ($c) => $c['key'] === 'accessory')->all();
    $page->set('data.categories', $state)->call('next')->assertHasNoErrors();

    expect(ProductCategory::keyed('accessory'))->not->toBeNull();
});

it('summarizes the counter products', function () {
    $admin = counterAdmin();
    $step = new CounterProductsStep;
    expect($step->isComplete($admin->company))->toBeFalse();

    Livewire::test(Onboarding::class)->call('next');

    expect($step->isComplete($admin->company))->toBeTrue()
        ->and($step->summary($admin->company))->toBe(__('app.onboarding.counter_products.summary', ['products' => 8, 'categories' => 6]));
});

it('rejects a category name another category already uses', function () {
    counterAdmin();
    $page = Livewire::test(Onboarding::class);

    $state = $page->get('data.categories');
    $firstKey = array_key_first($state);
    $state[$firstKey]['name'] = 'Lentes';
    $page->set('data.categories', $state)->call('next')
        ->assertHasErrors(["data.categories.{$firstKey}.name" => 'unique'])
        ->assertSet('step', CounterProductsStep::key());

    expect(ProductCategory::where('name', 'Lentes')->count())->toBe(1);
});

it('reports a removed category added again under the same name instead of failing', function () {
    counterAdmin();
    $page = Livewire::test(Onboarding::class)->call('next')->call('previous');

    $state = collect($page->get('data.categories'))
        ->reject(fn ($c) => $c['key'] === 'bag')
        ->push(['category_id' => null, 'key' => null, 'is_locked' => false, 'name' => 'Bolsas', 'products' => []])
        ->all();
    $page->set('data.categories', $state)->call('next')->assertHasErrors();

    expect(ProductCategory::where('name', 'Bolsas')->count())->toBe(1);
});

it('only offers to remove categories that would really be removed', function () {
    counterAdmin();
    $page = Livewire::test(Onboarding::class);
    $keyOf = fn (string $categoryKey) => collect($page->get('data.categories'))->search(fn ($c) => $c['key'] === $categoryKey);

    $page->assertActionHidden(TestAction::make('delete')->schemaComponent('categories')->arguments(['item' => $keyOf('accessory')]))
        ->assertActionVisible(TestAction::make('delete')->schemaComponent('categories')->arguments(['item' => $keyOf('case')]));
});

it('locks the products a combo uses and never deletes them', function () {
    $admin = counterAdmin();
    $page = Livewire::test(Onboarding::class)->call('next');
    ReferenceKit::installFor($admin->company);
    $page->call('previous');

    $state = $page->get('data.categories');
    $caseKey = collect($state)->search(fn ($c) => $c['key'] === 'case');
    $products = collect($state[$caseKey]['products']);
    $smallKey = $products->search(fn ($p) => $p['name'] === 'Estuche pequeño');
    $largeKey = $products->search(fn ($p) => $p['name'] === 'Estuche grande');

    expect($products[$smallKey]['is_locked'])->toBeTrue()
        ->and($products[$largeKey]['is_locked'])->toBeFalse();
    $page->assertActionHidden(TestAction::make('delete')->schemaComponent("categories.{$caseKey}.products")->arguments(['item' => $smallKey]))
        ->assertActionVisible(TestAction::make('delete')->schemaComponent("categories.{$caseKey}.products")->arguments(['item' => $largeKey]));

    unset($state[$caseKey]['products'][$smallKey], $state[$caseKey]['products'][$largeKey]);
    $page->set('data.categories', $state)->call('next')->assertHasNoErrors();

    expect(Product::where('name', 'Estuche pequeño')->exists())->toBeTrue()
        ->and(Product::where('name', 'Estuche grande')->exists())->toBeFalse();
});

it('leaves frames and sunglasses to their own resource', function () {
    counterAdmin();
    $frame = Product::factory()->create(['product_category_id' => ProductCategory::keyed('frame')->id, 'is_active' => true]);
    $page = Livewire::test(Onboarding::class);

    expect(collect($page->get('data.categories'))->pluck('key'))->not->toContain('frame')->not->toContain('sunglasses');

    $page->call('next')->assertHasNoErrors()->call('previous');

    expect(collect($page->get('data.categories'))->pluck('key'))->not->toContain('frame')->not->toContain('sunglasses')
        ->and(Product::whereKey($frame->id)->exists())->toBeTrue();
});
