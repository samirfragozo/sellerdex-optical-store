<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\CreatesNewUsers;

uses(RefreshDatabase::class);

it('creates a company and admin user on registration', function () {
    $this->post('/register', [
        'company_name' => 'Óptica Sur',
        'name' => 'Ana García',
        'email' => 'ana@opticasur.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertRedirect('/admin');

    $company = Company::where('name', 'Óptica Sur')->first();
    expect($company)->not->toBeNull()
        ->and($company->is_active)->toBeTrue();

    $user = User::where('email', 'ana@opticasur.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->company_id)->toBe($company->id)
        ->and($user->hasRole('admin'))->toBeTrue();
});

it('does not create orphaned company when email already taken', function () {
    // La validación de email único ocurre ANTES de DB::transaction,
    // por lo que nunca se escribe ninguna empresa.
    User::factory()->create(['email' => 'dup@test.com']);

    $this->post('/register', [
        'company_name' => 'Óptica Norte',
        'name' => 'Juan',
        'email' => 'dup@test.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertSessionHasErrors('email');

    expect(Company::where('name', 'Óptica Norte')->exists())->toBeFalse();
});

it('rolls back company creation if user insertion fails inside transaction', function () {
    // Reemplazamos la acción con una que crea la empresa y luego lanza,
    // para verificar que DB::transaction revierte la empresa.
    $this->app->bind(CreatesNewUsers::class, function () {
        return new class implements CreatesNewUsers
        {
            public function create(array $input): User
            {
                return DB::transaction(function () use ($input): User {
                    Company::create(['name' => $input['company_name'], 'is_active' => true, 'plan' => 'free']);
                    throw new RuntimeException('fallo simulado en inserción de usuario');
                });
            }
        };
    });

    try {
        $this->post('/register', [
            'company_name' => 'Óptica Rota',
            'name' => 'Test',
            'email' => 'rota@test.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);
    } catch (Throwable) {
        // La excepción puede propagarse o devolver 500; en ambos casos verificamos el DB.
    }

    expect(Company::where('name', 'Óptica Rota')->exists())->toBeFalse();
});

it('uses an Inertia location visit when redirecting to the admin panel', function () {
    $this->withHeaders(['X-Inertia' => 'true'])
        ->post('/register', [
            'company_name' => 'Óptica Este',
            'name' => 'Carla',
            'email' => 'carla@opticaeste.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', '/admin');
});

it('requires a company name', function () {
    $this->post('/register', [
        'name' => 'Juan',
        'email' => 'juan@test.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertSessionHasErrors('company_name');
});
