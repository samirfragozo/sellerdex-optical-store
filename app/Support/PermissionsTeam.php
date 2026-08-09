<?php

namespace App\Support;

use App\Models\Company;
use Closure;
use Spatie\Permission\PermissionRegistrar;

class PermissionsTeam
{
    /**
     * Run $callback with the permissions team context forced to $company
     * (or to "no company" when null), regardless of who is authenticated.
     * Always restores the previous context afterward, even on exception.
     */
    public static function runAs(?Company $company, Closure $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($company?->id);

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
