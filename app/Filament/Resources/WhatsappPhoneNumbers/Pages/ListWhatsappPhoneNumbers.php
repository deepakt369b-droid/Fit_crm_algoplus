<?php

namespace App\Filament\Resources\WhatsappPhoneNumbers\Pages;

use App\Filament\Resources\WhatsappPhoneNumbers\WhatsappPhoneNumberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWhatsappPhoneNumbers extends ListRecords
{
    protected static string $resource = WhatsappPhoneNumberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('app.actions.new', ['resource' => WhatsappPhoneNumberResource::getModelLabel()]))
                ->modalWidth('lg')
                ->createAnother(false),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.marketing'),
            WhatsappPhoneNumberResource::getNavigationLabel(),
        ];
    }
}
