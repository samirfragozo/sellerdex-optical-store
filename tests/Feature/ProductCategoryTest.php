<?php

use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\ProductCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('las categorías de producto son exclusivas del admin', function () {
    expect(User::factory()->seller()->create()->can('ViewAny:ProductCategory'))->toBeFalse()
        ->and(User::factory()->admin()->create()->can('ViewAny:ProductCategory'))->toBeTrue();
});

it('siembra las categorías de producto base sin duplicar', function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCategorySeeder::class);

    expect(ProductCategory::pluck('name')->all())
        ->toContain('Lente', 'Montura', 'Accesorio', 'Servicio')
        ->not->toContain('Filtro')
        ->not->toContain('Promoción')
        ->and(ProductCategory::where('name', 'Lente')->count())->toBe(1);
});

it('agrega key y flags de regla a las categorías', function () {
    foreach (['key', 'is_system', 'requires_prescription', 'generates_lab_order', 'is_made_to_order'] as $column) {
        expect(Schema::hasColumn('product_categories', $column))->toBeTrue();
    }
});

it('resuelve una categoría por su key estable', function () {
    $category = ProductCategory::factory()->create(['name' => 'Lente', 'key' => 'lens']);

    expect(ProductCategory::keyed('lens')->is($category))->toBeTrue()
        ->and(ProductCategory::keyed('inexistente'))->toBeNull();
});

it('mantiene el key aunque se renombre la categoría', function () {
    $category = ProductCategory::factory()->create(['name' => 'Lente', 'key' => 'lens']);
    $category->update(['name' => 'Lentes oftálmicos']);

    expect(ProductCategory::keyed('lens')->is($category))->toBeTrue();
});

it('no permite borrar una categoría de sistema', function () {
    $system = ProductCategory::factory()->create(['key' => 'lens', 'is_system' => true]);

    expect($system->delete())->toBeFalse()
        ->and(ProductCategory::whereKey($system->id)->exists())->toBeTrue();
});

it('siembra las categorías núcleo con key, flags y marca de sistema', function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCategorySeeder::class); // idempotente

    $lens = ProductCategory::keyed('lens');

    expect(ProductCategory::pluck('key')->all())->toContain('lens', 'frame', 'accessory', 'service')
        ->and($lens->is_system)->toBeTrue()
        ->and($lens->requires_prescription)->toBeTrue()
        ->and($lens->generates_lab_order)->toBeTrue()
        ->and($lens->is_made_to_order)->toBeTrue()
        ->and(ProductCategory::keyed('frame')->is_system)->toBeTrue()
        ->and(ProductCategory::keyed('frame')->requires_prescription)->toBeFalse()
        ->and(ProductCategory::where('key', 'lens')->count())->toBe(1);
});
