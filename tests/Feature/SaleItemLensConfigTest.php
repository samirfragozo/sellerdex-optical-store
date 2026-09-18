<?php

use App\Models\SaleItem;
use App\Models\SaleItemLensConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('una línea de venta con lensConfig se reconoce como lente', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $item = SaleItem::factory()->create();
    expect($item->isLens())->toBeFalse();

    SaleItemLensConfig::factory()->create(['sale_item_id' => $item->id]);
    $item->refresh();

    expect($item->isLens())->toBeTrue()
        ->and($item->lensConfig)->not->toBeNull();
});

it('la configuración de lente carga sus tratamientos', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $config = SaleItemLensConfig::factory()->create();
    $config->treatments()->create([
        'lens_treatment_id' => null,
        'name' => 'Antirreflejo',
        'price' => 50000,
        'cost' => 20000,
    ]);

    expect($config->treatments()->count())->toBe(1);
});
