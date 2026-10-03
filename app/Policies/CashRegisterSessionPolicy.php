<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CashRegisterSession;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CashRegisterSessionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CashRegisterSession');
    }

    /** The cashier sees their own session; admins see their shop's. Route binding skips CompanyScope, so check the shop here. */
    public function view(AuthUser $authUser, CashRegisterSession $cashRegisterSession): bool
    {
        if ($cashRegisterSession->company_id !== $authUser->company_id) {
            return false;
        }

        return $cashRegisterSession->user_id === $authUser->getKey() || $authUser->can('View:CashRegisterSession');
    }

    public function update(AuthUser $authUser, CashRegisterSession $cashRegisterSession): bool
    {
        return $authUser->can('Update:CashRegisterSession');
    }
}
