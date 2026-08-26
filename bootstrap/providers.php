<?php

return [
    App\Providers\AppServiceProvider::class,
    // Must boot BEFORE AdminPanelProvider: the admin panel's tenanted
    // routes (/{tenant}/...) are registered dynamically and otherwise
    // shadow this panel's static /superadmin/... routes (the tenant
    // lookup for slug "superadmin" then 404s every page).
    App\Providers\Filament\SuperAdminPanelProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
];
