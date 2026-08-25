<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\WhatsappBroadcast;
use Illuminate\Auth\Access\HandlesAuthorization;

class WhatsappBroadcastPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WhatsappBroadcast');
    }

    public function view(AuthUser $authUser, WhatsappBroadcast $whatsappBroadcast): bool
    {
        return $authUser->can('View:WhatsappBroadcast');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WhatsappBroadcast');
    }

    public function update(AuthUser $authUser, WhatsappBroadcast $whatsappBroadcast): bool
    {
        return $authUser->can('Update:WhatsappBroadcast');
    }

    public function delete(AuthUser $authUser, WhatsappBroadcast $whatsappBroadcast): bool
    {
        return $authUser->can('Delete:WhatsappBroadcast');
    }

    public function restore(AuthUser $authUser, WhatsappBroadcast $whatsappBroadcast): bool
    {
        return $authUser->can('Restore:WhatsappBroadcast');
    }

    public function forceDelete(AuthUser $authUser, WhatsappBroadcast $whatsappBroadcast): bool
    {
        return $authUser->can('ForceDelete:WhatsappBroadcast');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WhatsappBroadcast');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WhatsappBroadcast');
    }

    public function replicate(AuthUser $authUser, WhatsappBroadcast $whatsappBroadcast): bool
    {
        return $authUser->can('Replicate:WhatsappBroadcast');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WhatsappBroadcast');
    }
}
