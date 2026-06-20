<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('permite a un admin activo acceder al panel', function () {
    $user = User::factory()->admin()->create(['is_active' => true]);
    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeTrue();
});

it('permite a un vendedor activo acceder al panel', function () {
    $user = User::factory()->seller()->create(['is_active' => true]);
    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeTrue();
});

it('niega el acceso a un usuario inactivo', function () {
    $user = User::factory()->admin()->create(['is_active' => false]);
    expect($user->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
});

it('expone helpers de rol', function () {
    expect(User::factory()->admin()->create()->isAdmin())->toBeTrue()
        ->and(User::factory()->seller()->create()->isSeller())->toBeTrue();
});
