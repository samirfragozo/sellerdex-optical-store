<?php

use App\Models\LensPackage;
use App\Models\LensTreatment;
use App\Models\User;
use Database\Seeders\LensPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crea tratamientos y paquetes de lente con precio y costo', function (string $model) {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $record = $model::factory()->create(['name' => 'Antirreflejo', 'price' => 50000, 'cost' => 20000]);

    expect($record->company_id)->toBe($user->company_id)
        ->and($record->price)->toBe(50000)
        ->and($record->cost)->toBe(20000);
})
    ->with([LensTreatment::class])
    ->with([LensPackage::class]);

it('siembra un paquete Básico por empresa sin duplicar', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $this->seed(LensPackageSeeder::class);
    $this->seed(LensPackageSeeder::class);

    expect(LensPackage::where('company_id', $user->company_id)->where('name', 'Básico')->count())->toBe(1);
});
