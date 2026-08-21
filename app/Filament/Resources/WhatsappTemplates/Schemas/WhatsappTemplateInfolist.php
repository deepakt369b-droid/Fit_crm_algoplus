<?php

namespace App\Filament\Resources\WhatsappTemplates\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WhatsappTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label(__('app.fields.name')),
                        TextEntry::make('language')->label(__('app.whatsapp.language')),
                        TextEntry::make('category')->label(__('app.whatsapp.category')),
                        TextEntry::make('status')->label(__('app.fields.status'))->badge(),
                        TextEntry::make('components')
                            ->label(__('app.whatsapp.components'))
                            ->formatStateUsing(fn (mixed $state): string => json_encode($state, JSON_PRETTY_PRINT) ?: '—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
