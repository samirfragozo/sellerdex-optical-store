<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LensOrder;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LensOrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LensOrder');
    }

    public function view(AuthUser $authUser, LensOrder $lensOrder): bool
    {
        return $authUser->can('View:LensOrder');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LensOrder');
    }

    public function update(AuthUser $authUser, LensOrder $lensOrder): bool
    {
        return $authUser->can('Update:LensOrder');
    }

    public function delete(AuthUser $authUser, LensOrder $lensOrder): bool
    {
        return $authUser->can('Delete:LensOrder');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LensOrder');
    }

    public function restore(AuthUser $authUser, LensOrder $lensOrder): bool
    {
        return $authUser->can('Restore:LensOrder');
    }

    public function forceDelete(AuthUser $authUser, LensOrder $lensOrder): bool
    {
        return $authUser->can('ForceDelete:LensOrder');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LensOrder');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LensOrder');
    }

    public function replicate(AuthUser $authUser, LensOrder $lensOrder): bool
    {
        return $authUser->can('Replicate:LensOrder');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LensOrder');
    }
}
