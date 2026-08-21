<?php

namespace App\Filament\Resources\WhatsappConversations\Pages;

use App\Filament\Resources\WhatsappConversations\WhatsappConversationResource;
use Filament\Resources\Pages\ListRecords;

class ListWhatsappConversations extends ListRecords
{
    protected static string $resource = WhatsappConversationResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.marketing'),
            WhatsappConversationResource::getNavigationLabel(),
        ];
    }
}
