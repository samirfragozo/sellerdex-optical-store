<?php

namespace App\Support;

use App\Models\Company;
use Closure;
use Spatie\Permission\PermissionRegistrar;

class PermissionsTeam
{
    /**
     * Tracks nesting depth so a top-level call can tell it's the outermost
     * one and restore the resolver to "auto" (null — CompanyTeamResolver
     * then re-derives from Auth::user() dynamically) rather than to a
     * resolved snapshot that would otherwise permanently override the
     * resolver, silently ignoring any later change of authenticated user.
     */
    private static int $depth = 0;

    /**
     * Run $callback with the permissions team context forced to $company
     * (or to "no company" when null), regardless of who is authenticated.
     * Always restores the previous context afterward, even on exception —
     * to "auto" (re-derive from Auth::user()) at the outermost call, or to
     * the enclosing runAs() call's context when nested.
     */
    public static function runAs(?Company $company, Closure $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        self::$depth++;
        $registrar->setPermissionsTeamId($company?->id);

        try {
            return $callback();
        } finally {
            self::$depth--;
            $registrar->setPermissionsTeamId(self::$depth === 0 ? null : $previous);
        }
    }
}
