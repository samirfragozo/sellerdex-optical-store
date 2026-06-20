<?php

use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\ProductCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('las categorías de producto son exclusivas del admin', function () {
    expect(User::factory()->seller()->create()->can('ViewAny:ProductCategory'))->toBeFalse()
        ->and(User::factory()->admin()->create()->can('ViewAny:ProductCategory'))->toBeTrue();
});

it('siembra las categorías de producto base sin duplicar', function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCategorySeeder::class);

    expect(ProductCategory::pluck('name')->all())
        ->toContain('Lente', 'Montura', 'Filtro', 'Accesorio', 'Promoción', 'Servicio')
        ->and(ProductCategory::where('name', 'Lente')->count())->toBe(1);
});
