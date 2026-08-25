<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\WhatsappAutomation;
use Illuminate\Auth\Access\HandlesAuthorization;

class WhatsappAutomationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WhatsappAutomation');
    }

    public function view(AuthUser $authUser, WhatsappAutomation $whatsappAutomation): bool
    {
        return $authUser->can('View:WhatsappAutomation');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WhatsappAutomation');
    }

    public function update(AuthUser $authUser, WhatsappAutomation $whatsappAutomation): bool
    {
        return $authUser->can('Update:WhatsappAutomation');
    }

    public function delete(AuthUser $authUser, WhatsappAutomation $whatsappAutomation): bool
    {
        return $authUser->can('Delete:WhatsappAutomation');
    }

    public function restore(AuthUser $authUser, WhatsappAutomation $whatsappAutomation): bool
    {
        return $authUser->can('Restore:WhatsappAutomation');
    }

    public function forceDelete(AuthUser $authUser, WhatsappAutomation $whatsappAutomation): bool
    {
        return $authUser->can('ForceDelete:WhatsappAutomation');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WhatsappAutomation');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WhatsappAutomation');
    }

    public function replicate(AuthUser $authUser, WhatsappAutomation $whatsappAutomation): bool
    {
        return $authUser->can('Replicate:WhatsappAutomation');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WhatsappAutomation');
    }
}
