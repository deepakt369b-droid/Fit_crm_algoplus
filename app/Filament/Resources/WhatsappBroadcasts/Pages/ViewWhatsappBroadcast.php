<?php

namespace App\Filament\Resources\WhatsappBroadcasts\Pages;

use App\Filament\Resources\WhatsappBroadcasts\Schemas\WhatsappBroadcastInfolist;
use App\Filament\Resources\WhatsappBroadcasts\WhatsappBroadcastResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewWhatsappBroadcast extends ViewRecord
{
    protected static string $resource = WhatsappBroadcastResource::class;

    public function infolist(Schema $schema): Schema
    {
        return WhatsappBroadcastInfolist::configure($schema);
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.marketing'),
            WhatsappBroadcastResource::getNavigationLabel(),
        ];
    }
}
