<?php

namespace App\Filament\Resources\WhatsappAutomations\Pages;

use App\Filament\Resources\WhatsappAutomations\WhatsappAutomationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewWhatsappAutomation extends ViewRecord
{
    protected static string $resource = WhatsappAutomationResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.marketing'),
            WhatsappAutomationResource::getNavigationLabel(),
        ];
    }
}
