<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CashClose;
use App\Models\User;

class CashClosePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, CashClose $cashClose): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, CashClose $cashClose): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, CashClose $cashClose): bool
    {
        return $user->isAdmin();
    }
}
