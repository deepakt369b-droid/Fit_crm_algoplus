<?php

namespace App\Filament\Resources\WhatsappContacts\Pages;

use App\Filament\Resources\WhatsappContacts\WhatsappContactResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWhatsappContacts extends ListRecords
{
    protected static string $resource = WhatsappContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('app.actions.new', ['resource' => WhatsappContactResource::getModelLabel()]))
                ->modalWidth('md')
                ->createAnother(false),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.marketing'),
            WhatsappContactResource::getNavigationLabel(),
        ];
    }
}
