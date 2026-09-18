<?php

use App\Models\LensPackage;
use App\Models\LensTreatment;
use App\Models\User;
use Database\Seeders\LensPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crea tratamientos de lente con precio y costo', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $record = LensTreatment::factory()->create(['name' => 'Antirreflejo', 'price' => 50000, 'cost' => 20000]);

    expect($record->company_id)->toBe($user->company_id)
        ->and($record->price)->toBe(50000)
        ->and($record->cost)->toBe(20000);
});

it('crea paquetes de lente con precio y costo', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $record = LensPackage::factory()->create(['name' => 'Antirreflejo', 'price' => 50000, 'cost' => 20000]);

    expect($record->company_id)->toBe($user->company_id)
        ->and($record->price)->toBe(50000)
        ->and($record->cost)->toBe(20000);
});

it('siembra un paquete Básico por empresa sin duplicar', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $this->seed(LensPackageSeeder::class);
    $this->seed(LensPackageSeeder::class);

    expect(LensPackage::where('company_id', $user->company_id)->where('name', 'Básico')->count())->toBe(1);
});
