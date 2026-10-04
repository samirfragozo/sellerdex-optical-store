<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\WarrantyClaim;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class WarrantyClaimPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WarrantyClaim');
    }

    public function view(AuthUser $authUser, WarrantyClaim $warrantyClaim): bool
    {
        return $authUser->can('View:WarrantyClaim') && $warrantyClaim->company_id === $authUser->company_id;
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WarrantyClaim');
    }

    public function update(AuthUser $authUser, WarrantyClaim $warrantyClaim): bool
    {
        return $authUser->can('Update:WarrantyClaim');
    }

    public function delete(AuthUser $authUser, WarrantyClaim $warrantyClaim): bool
    {
        return $authUser->can('Delete:WarrantyClaim');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:WarrantyClaim');
    }

    public function restore(AuthUser $authUser, WarrantyClaim $warrantyClaim): bool
    {
        return $authUser->can('Restore:WarrantyClaim');
    }

    public function forceDelete(AuthUser $authUser, WarrantyClaim $warrantyClaim): bool
    {
        return $authUser->can('ForceDelete:WarrantyClaim');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WarrantyClaim');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WarrantyClaim');
    }

    public function replicate(AuthUser $authUser, WarrantyClaim $warrantyClaim): bool
    {
        return $authUser->can('Replicate:WarrantyClaim');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WarrantyClaim');
    }
}
