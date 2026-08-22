<?php

namespace App\Filament\Resources\WhatsappAutomations\Pages;

use App\Filament\Resources\WhatsappAutomations\WhatsappAutomationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWhatsappAutomations extends ListRecords
{
    protected static string $resource = WhatsappAutomationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('app.actions.new', ['resource' => WhatsappAutomationResource::getModelLabel()]))
                ->modalWidth('4xl')
                ->createAnother(false),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.marketing'),
            WhatsappAutomationResource::getNavigationLabel(),
        ];
    }
}
