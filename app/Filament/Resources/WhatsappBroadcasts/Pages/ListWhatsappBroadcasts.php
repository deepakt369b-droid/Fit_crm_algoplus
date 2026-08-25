<?php

namespace App\Filament\Resources\WhatsappBroadcasts\Pages;

use App\Filament\Resources\WhatsappBroadcasts\WhatsappBroadcastResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWhatsappBroadcasts extends ListRecords
{
    protected static string $resource = WhatsappBroadcastResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('app.actions.new', ['resource' => WhatsappBroadcastResource::getModelLabel()]))
                ->modalWidth('lg')
                ->createAnother(false),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.marketing'),
            WhatsappBroadcastResource::getNavigationLabel(),
        ];
    }
}
