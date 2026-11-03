<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LabPriceSheet;
use Illuminate\Foundation\Auth\User as AuthUser;

/** A lab's price sheet is managed with the supplier it belongs to, so it follows the supplier permissions. */
class LabPriceSheetPolicy
{
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Supplier');
    }

    public function view(AuthUser $authUser, LabPriceSheet $sheet): bool
    {
        return $authUser->can('View:Supplier');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Supplier');
    }

    public function update(AuthUser $authUser, LabPriceSheet $sheet): bool
    {
        return $authUser->can('Update:Supplier');
    }

    public function delete(AuthUser $authUser, LabPriceSheet $sheet): bool
    {
        return $authUser->can('Update:Supplier');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Supplier');
    }
}
