<?php

namespace App\Models\Scopes;

use App\Contracts\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Scopes queries to the current tenant's gym (branch).
 *
 * Resolves through the bound {@see TenantContext}. When it reports no gym
 * (console/scheduled commands, superadmin context, or single-tenant/OSS
 * installs where no TenantContext is bound), the query is left unscoped so
 * those contexts continue to operate across every branch.
 */
class GymScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound(TenantContext::class)) {
            return;
        }

        $gymId = app(TenantContext::class)->gymId();

        if ($gymId !== null) {
            $builder->where($model->qualifyColumn('gym_id'), $gymId);
        }
    }
}
