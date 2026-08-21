<?php

namespace App\Filament\Resources\Gyms\Pages;

use App\Filament\Resources\Gyms\GymResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGyms extends ListRecords
{
    protected static string $resource = GymResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-m-plus')
                ->label(__('app.actions.new', ['resource' => GymResource::getModelLabel()]))
                ->modalHeading(__('app.actions.new', ['resource' => GymResource::getModelLabel()]))
                ->modalWidth('lg')
                ->createAnother(false),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            GymResource::getNavigationLabel(),
        ];
    }
}
