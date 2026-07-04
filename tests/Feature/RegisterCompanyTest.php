<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

it('rolls back if user creation fails', function () {
    // Forzar email duplicado
    User::factory()->create(['email' => 'dup@test.com']);

    $this->post('/register', [
        'company_name' => 'Óptica Norte',
        'name' => 'Juan',
        'email' => 'dup@test.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertSessionHasErrors('email');

    // No debe haber quedado empresa huérfana
    expect(Company::where('name', 'Óptica Norte')->exists())->toBeFalse();
});

it('requires a company name', function () {
    $this->post('/register', [
        'name' => 'Juan',
        'email' => 'juan@test.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ])->assertSessionHasErrors('company_name');
});
