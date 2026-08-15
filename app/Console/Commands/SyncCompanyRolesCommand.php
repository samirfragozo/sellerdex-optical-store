<?php

namespace App\Console\Commands;

use App\Actions\ProvisionCompanyRoles;
use App\Models\Company;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Re-syncs the admin/seller roles for every existing company against the
 * current permission catalog. ProvisionCompanyRoles only runs automatically
 * when a company is first created, so a permission added for a new resource
 * never reaches companies that already existed — run this once after adding
 * one.
 */
#[Signature('companies:sync-roles')]
#[Description('Re-provision the admin/seller roles and permissions for every company')]
class SyncCompanyRolesCommand extends Command
{
    public function handle(): int
    {
        $companies = Company::all();

        $this->components->info("Syncing roles for {$companies->count()} companies.");

        foreach ($companies as $company) {
            $this->components->task($company->name, fn () => (new ProvisionCompanyRoles)->handle($company));
        }

        return self::SUCCESS;
    }
}
