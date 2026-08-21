<?php

namespace App\Services;

use App\Contracts\TenantContext;
use App\Models\Gym;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Resolves the current branch (Gym) for multi-branch FitCRM installs.
 *
 * Resolution order:
 *  1. Console/scheduled commands (outside of tests) run across every branch.
 *  2. Inside a Filament panel with an active tenant, that tenant is the branch.
 *  3. For an authenticated API/panel user, a device token's own gym (see the
 *     attendance gate integration) takes precedence, then the user's home
 *     branch.
 *  4. Otherwise (e.g. an anonymous login request), no branch is assumed —
 *     lookups run unscoped, since a user's email is globally unique.
 */
class ResolvedTenantContext implements TenantContext
{
    public function gymId(): ?int
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return null;
        }

        $panelTenantId = $this->panelTenantId();

        if ($panelTenantId !== null) {
            return $panelTenantId;
        }

        return $this->authenticatedUserGymId();
    }

    private function panelTenantId(): ?int
    {
        try {
            $tenant = Filament::getTenant();
        } catch (Throwable) {
            return null;
        }

        return $tenant instanceof Gym ? $tenant->id : null;
    }

    private function authenticatedUserGymId(): ?int
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        $token = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;

        if ($token !== null) {
            $tokenGymId = $token->getAttribute('gym_id');

            if ($tokenGymId !== null) {
                return (int) $tokenGymId;
            }
        }

        return $user->gym_id !== null ? (int) $user->gym_id : null;
    }
}
