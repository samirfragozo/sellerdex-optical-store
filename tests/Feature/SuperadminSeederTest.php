<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

it('does nothing outside production when no credentials are configured', function () {
    config(['app.superadmin.email' => null, 'app.superadmin.password' => null]);

    $this->seed(SuperadminSeeder::class);

    expect(User::count())->toBe(0);
});

it('creates the superadmin user when credentials are configured', function () {
    config(['app.superadmin.email' => 'root@optica.test', 'app.superadmin.password' => 'S3cret-Pass!']);

    $this->seed(SuperadminSeeder::class);

    $superadmin = User::where('email', 'root@optica.test')->first();

    expect($superadmin)->not->toBeNull()
        ->and($superadmin->company_id)->toBeNull()
        ->and($superadmin->is_active)->toBeTrue()
        ->and($superadmin->hasRole(User::ROLE_SUPERADMIN))->toBeTrue();
});

it('is idempotent', function () {
    config(['app.superadmin.email' => 'root@optica.test', 'app.superadmin.password' => 'S3cret-Pass!']);

    $this->seed(SuperadminSeeder::class);
    $this->seed(SuperadminSeeder::class);

    expect(User::where('email', 'root@optica.test')->count())->toBe(1);
});

it('fails when seeding production without credentials configured', function () {
    config(['app.superadmin.email' => null, 'app.superadmin.password' => null]);
    app()->detectEnvironment(fn () => 'production');

    // Called directly (not via the artisan db:seed command) to test the
    // seeder's own guard, independent of Artisan's interactive production prompt.
    (new SuperadminSeeder)->run();
})->throws(RuntimeException::class);

it('creates the superadmin when seeding production with credentials configured', function () {
    config(['app.superadmin.email' => 'root@optica.test', 'app.superadmin.password' => 'S3cret-Pass!']);
    app()->detectEnvironment(fn () => 'production');

    (new SuperadminSeeder)->run();

    expect(User::where('email', 'root@optica.test')->exists())->toBeTrue();
});
