<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\User;
use App\Support\PermissionsTeam;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Creates (or finds) the admin and seller roles for one company, synced to
 * the standard permission sets. Idempotent — safe to call more than once
 * for the same company. Called once right after a Company is created
 * (self-registration and the superadmin panel), and again by the
 * admin()/seller() test factory states so tests don't depend on a seeder
 * having run first.
 */
class ProvisionCompanyRoles
{
    public function handle(Company $company): void
    {
        PermissionsTeam::runAs($company, function (): void {
            foreach (RolesAndPermissionsSeeder::SUBJECTS as $subject) {
                foreach (RolesAndPermissionsSeeder::ACTIONS as $action) {
                    Permission::findOrCreate("{$action}:{$subject}", 'web');
                }
            }

            $admin = Role::findOrCreate(User::ROLE_ADMIN, 'web');
            $admin->syncPermissions(Permission::all());

            $seller = Role::findOrCreate(User::ROLE_SELLER, 'web');
            $seller->syncPermissions(RolesAndPermissionsSeeder::SELLER_PERMISSIONS);
        });
    }
}
