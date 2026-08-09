<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * Resolves the Spatie "team" as the authenticated user's company_id, so
 * every hasRole()/can() check is scoped to the right company without
 * every call site having to think about it. Code that needs to act as a
 * specific company outside of that company's own request (registration,
 * seeders, the superadmin panel) must go through PermissionsTeam::runAs().
 */
class CompanyTeamResolver implements PermissionsTeamResolver
{
    private int|string|null $teamId = null;

    public function setPermissionsTeamId(int|string|Model|null $id): void
    {
        $this->teamId = $id instanceof Model ? $id->getKey() : $id;
    }

    public function getPermissionsTeamId(): int|string|null
    {
        return $this->teamId ?? Auth::user()?->company_id;
    }
}
