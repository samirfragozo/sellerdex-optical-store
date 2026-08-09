<?php

use App\Models\User;
use App\Support\PermissionsTeam;
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

it('redirige el login de filament hacia el login del pos', function () {
    $this->get('/admin/login')->assertRedirect(route('login'));
});

it('un usuario ya autenticado que visita el login de filament entra al panel', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get('/admin/login')
        ->assertRedirect('/admin');
});

it('expone helpers de rol', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->seller()->create();

    expect(PermissionsTeam::runAs($admin->company, fn () => $admin->isAdmin()))->toBeTrue()
        ->and(PermissionsTeam::runAs($seller->company, fn () => $seller->isSeller()))->toBeTrue();
});
