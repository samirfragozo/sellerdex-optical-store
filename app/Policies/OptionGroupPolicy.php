<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OptionGroup;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class OptionGroupPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OptionGroup');
    }

    public function view(AuthUser $authUser, OptionGroup $optionGroup): bool
    {
        return $authUser->can('View:OptionGroup');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OptionGroup');
    }

    public function update(AuthUser $authUser, OptionGroup $optionGroup): bool
    {
        return $authUser->can('Update:OptionGroup');
    }

    public function delete(AuthUser $authUser, OptionGroup $optionGroup): bool
    {
        return $authUser->can('Delete:OptionGroup');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OptionGroup');
    }

    public function restore(AuthUser $authUser, OptionGroup $optionGroup): bool
    {
        return $authUser->can('Restore:OptionGroup');
    }

    public function forceDelete(AuthUser $authUser, OptionGroup $optionGroup): bool
    {
        return $authUser->can('ForceDelete:OptionGroup');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OptionGroup');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OptionGroup');
    }

    public function replicate(AuthUser $authUser, OptionGroup $optionGroup): bool
    {
        return $authUser->can('Replicate:OptionGroup');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OptionGroup');
    }
}
