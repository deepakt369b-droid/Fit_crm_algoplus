<?php

namespace App\Services;

use App\Contracts\TenantContext;
use App\Models\Device;
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
 *  3. For an authenticated API request — a staff user or a paired gate
 *     Device (see the attendance gate integration; both are Sanctum
 *     tokenables) — that authenticatable's own branch.
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

        return $this->authenticatedGymId();
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

    private function authenticatedGymId(): ?int
    {
        $authenticatable = Auth::user();

        if (! $authenticatable instanceof User && ! $authenticatable instanceof Device) {
            return null;
        }

        $gymId = $authenticatable->getAttribute('gym_id');

        return $gymId !== null ? (int) $gymId : null;
    }
}
