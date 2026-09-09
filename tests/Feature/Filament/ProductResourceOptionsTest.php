<?php

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Company;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

it('lets an admin attach option groups to a product', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->forCompany($company)->admin()->create();
    $this->actingAs($admin);

    $product = Product::factory()->create();
    $group = OptionGroup::factory()->create(['name' => 'Filtro']);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm(['optionGroups' => [$group->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->optionGroups->pluck('id')->all())->toBe([$group->id]);
});
