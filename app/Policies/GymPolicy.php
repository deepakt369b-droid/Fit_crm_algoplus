<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Gym;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class GymPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Gym');
    }

    public function view(AuthUser $authUser, Gym $gym): bool
    {
        return $authUser->can('View:Gym');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Gym');
    }

    public function update(AuthUser $authUser, Gym $gym): bool
    {
        return $authUser->can('Update:Gym');
    }

    public function delete(AuthUser $authUser, Gym $gym): bool
    {
        return $authUser->can('Delete:Gym');
    }

    public function restore(AuthUser $authUser, Gym $gym): bool
    {
        return $authUser->can('Restore:Gym');
    }

    public function forceDelete(AuthUser $authUser, Gym $gym): bool
    {
        return $authUser->can('ForceDelete:Gym');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Gym');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Gym');
    }

    public function replicate(AuthUser $authUser, Gym $gym): bool
    {
        return $authUser->can('Replicate:Gym');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Gym');
    }
}
