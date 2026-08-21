<?php

namespace App\Models\Concerns;

use App\Contracts\TenantContext;
use App\Models\Gym;
use App\Models\Scopes\GymScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scope a model to its owning Gym (branch) and auto-fill gym_id on create.
 *
 * Models using this trait are automatically scoped to the current tenant via
 * {@see GymScope}. When the bound TenantContext reports no gym (console
 * commands, the superadmin panel, or single-tenant/OSS installs), records
 * are visible and creatable across every branch, exactly like every model
 * behaves today.
 */
trait BelongsToGym
{
    public static function bootBelongsToGym(): void
    {
        static::addGlobalScope(new GymScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('gym_id') !== null) {
                return;
            }

            if (! app()->bound(TenantContext::class)) {
                return;
            }

            $gymId = app(TenantContext::class)->gymId();

            if ($gymId !== null) {
                $model->setAttribute('gym_id', $gymId);
            }
        });
    }

    /**
     * @return BelongsTo<Gym, $this>
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }
}
