<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Shield generates permissions in the `Action:Subject` format (pascal case, ':' separator).
     */
    private const ACTIONS = [
        'ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny',
        'ForceDelete', 'ForceDeleteAny', 'Restore', 'RestoreAny', 'Reorder', 'Replicate',
    ];

    private const SUBJECTS = [
        'Customer', 'Expense', 'ExpenseCategory', 'Payment', 'PaymentMethod',
        'Prescription', 'Product', 'ProductCategory', 'Role', 'Sale', 'Supplier',
    ];

    /**
     * Seller permissions: manage customers, prescriptions, sales, and payments (no deletes),
     * and read-only access to the product catalog.
     * Sellers cannot delete sales, nor update or delete payments.
     *
     * @var list<string>
     */
    private const SELLER_PERMISSIONS = [
        'ViewAny:Customer', 'View:Customer', 'Create:Customer', 'Update:Customer',
        'ViewAny:Prescription', 'View:Prescription', 'Create:Prescription', 'Update:Prescription',
        'ViewAny:Product', 'View:Product',
        'ViewAny:Sale', 'View:Sale', 'Create:Sale', 'Update:Sale',
        'ViewAny:Payment', 'View:Payment', 'Create:Payment',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::SUBJECTS as $subject) {
            foreach (self::ACTIONS as $action) {
                Permission::findOrCreate("{$action}:{$subject}", 'web');
            }
        }

        // Admin is the super admin (intercepted by Shield via Gate::before); it is
        // granted every permission anyway for clarity.
        $admin = Role::findOrCreate(User::ROLE_ADMIN, 'web');
        $admin->syncPermissions(Permission::all());

        $seller = Role::findOrCreate(User::ROLE_SELLER, 'web');
        $seller->syncPermissions(self::SELLER_PERMISSIONS);
    }
}
