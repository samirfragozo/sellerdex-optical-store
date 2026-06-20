<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Sale $sale): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Sale $sale): bool
    {
        return true;
    }

    public function delete(User $user, Sale $sale): bool
    {
        return $user->isAdmin();
    }

    public function void(User $user, Sale $sale): bool
    {
        return $user->isAdmin();
    }
}
