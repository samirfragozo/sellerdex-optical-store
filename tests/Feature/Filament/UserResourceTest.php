<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Company;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('el admin ve el listado de usuarios', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(UserResource::getUrl())
        ->assertSuccessful();
});

it('el vendedor no puede acceder al listado de usuarios', function () {
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)
        ->get(UserResource::getUrl())
        ->assertForbidden();
});

it('el listado solo muestra usuarios de la company del admin', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $adminA = User::factory()->forCompany($companyA)->admin()->create(['name' => 'Admin A']);
    User::factory()->forCompany($companyB)->admin()->create(['name' => 'Admin B']);

    $this->actingAs($adminA);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$adminA])
        ->assertCanNotSeeTableRecords(User::withoutGlobalScopes()->where('id', '!=', $adminA->id)->get());
});

it('crear un usuario le asigna la company y el rol, y le envía un correo para definir contraseña', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Vendedor Nuevo',
            'email' => 'vendedor@example.com',
            'role' => User::ROLE_SELLER,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::withoutGlobalScopes()->where('email', 'vendedor@example.com')->firstOrFail();

    expect($user->company_id)->toBe($admin->company_id)
        ->and($user->hasRole(User::ROLE_SELLER))->toBeTrue()
        ->and(DB::table('password_reset_tokens')->where('email', 'vendedor@example.com')->exists())->toBeTrue();
});

it('editar un usuario permite cambiarle el rol', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->forCompany($admin->company)->seller()->create();
    $this->actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $seller->getRouteKey()])
        ->fillForm(['role' => User::ROLE_ADMIN])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($seller->fresh()->hasRole(User::ROLE_ADMIN))->toBeTrue()
        ->and($seller->fresh()->hasRole(User::ROLE_SELLER))->toBeFalse();
});

it('el botón de activar/desactivar cambia is_active', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->forCompany($admin->company)->seller()->create(['is_active' => true]);
    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('toggle_active')->table($seller));

    expect($seller->fresh()->is_active)->toBeFalse();
});

it('el admin no puede activar/desactivar su propio usuario', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('toggle_active')->table($admin));
});

it('el botón de eliminar está oculto si el usuario tiene actividad de negocio', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->forCompany($admin->company)->seller()->create();
    Payment::factory()->create(['company_id' => $admin->company_id, 'received_by' => $seller->id]);
    $this->actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $seller->getRouteKey()])
        ->assertActionHidden('delete');
});

it('el admin no puede eliminarse a sí mismo', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
        ->assertActionHidden('delete');
});

it('se puede eliminar un usuario sin actividad de negocio', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->forCompany($admin->company)->seller()->create();
    $this->actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $seller->getRouteKey()])
        ->assertActionVisible('delete');
});
