<?php

use App\Contracts\TenantContext;
use App\Providers\AppServiceProvider;
use App\Services\ResolvedTenantContext;
use Illuminate\Foundation\Application;

it('registers a singleton resolved tenant context for multi-branch installations', function (): void {
    $application = new Application;

    (new AppServiceProvider($application))->register();

    $tenantContext = $application->make(TenantContext::class);

    expect($tenantContext)
        ->toBeInstanceOf(ResolvedTenantContext::class)
        ->and($application->make(TenantContext::class))->toBe($tenantContext);
});

it('preserves a tenant context registered by an add-on', function (): void {
    $application = new Application;
    $tenantContext = new class implements TenantContext
    {
        public function gymId(): ?int
        {
            return 42;
        }
    };

    $application->singleton(TenantContext::class, fn (): TenantContext => $tenantContext);

    (new AppServiceProvider($application))->register();

    expect($application->make(TenantContext::class))
        ->toBe($tenantContext)
        ->and($application->make(TenantContext::class)->gymId())->toBe(42);
});
