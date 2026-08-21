<?php

namespace App\Filament\Resources\Gyms\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GymInfolist
{
    /**
     * Configure the gym (branch) "view" infolist schema.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label(__('app.fields.name')),
                        TextEntry::make('slug')->label(__('app.fields.slug')),
                        TextEntry::make('code')->label(__('app.fields.code')),
                        TextEntry::make('status')->label(__('app.fields.status')),
                        TextEntry::make('email')->label(__('app.fields.email')),
                        TextEntry::make('contact')->label(__('app.fields.contact')),
                        TextEntry::make('timezone')->label(__('app.fields.timezone')),
                        TextEntry::make('currency')->label(__('app.fields.currency')),
                        TextEntry::make('address')->label(__('app.fields.address'))->columnSpanFull(),
                    ]),
            ]);
    }
}
