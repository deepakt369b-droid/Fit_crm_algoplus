<?php

namespace App\Providers\Filament;

use App\Filament\Resources\Gyms\GymResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;

/**
 * Superadmin panel: cross-branch administration (creating branches,
 * managing users independent of any single branch's tenant context).
 *
 * Restricted to the `super_admin` role via {@see \App\Models\User::canAccessPanel()}.
 * Has no tenancy of its own — it is the one place that is deliberately
 * branch-agnostic. Reuses AdminPanelProvider's shared styling/middleware so
 * both panels look and behave identically, but registers its own, smaller
 * set of resources rather than auto-discovering every operational resource.
 */
class SuperAdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return (new AdminPanelProvider($this->app))->sharedPanelStyling($panel)
            ->id('superadmin')
            ->path('superadmin')
            ->resources([
                GymResource::class,
                UserResource::class,
            ])
            ->navigation(fn (NavigationBuilder $builder) => $builder->groups([
                NavigationGroup::make(__('app.navigation.groups.administration'))
                    ->icon('heroicon-o-building-office-2')
                    ->items([
                        ...GymResource::getNavigationItems(),
                        ...UserResource::getNavigationItems(),
                    ])
                    ->collapsed(false),
            ]));
    }
}
