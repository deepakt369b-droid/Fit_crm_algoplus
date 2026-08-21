<?php

namespace App\Filament\Resources\WhatsappBroadcasts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WhatsappBroadcastInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')->label(__('app.fields.name')),
                        TextEntry::make('status')->label(__('app.fields.status'))->badge(),
                        TextEntry::make('phoneNumber.display_phone_number')
                            ->label(__('app.resources.whatsapp_phone_numbers.singular')),
                        TextEntry::make('template.name')->label(__('app.resources.whatsapp_templates.singular')),
                        TextEntry::make('total_recipients')->label(__('app.whatsapp.recipients')),
                        TextEntry::make('sent_count')->label(__('app.whatsapp.sent')),
                        TextEntry::make('delivered_count')->label(__('app.whatsapp.delivered')),
                        TextEntry::make('read_count')->label(__('app.whatsapp.read')),
                        TextEntry::make('failed_count')->label(__('app.whatsapp.failed')),
                    ]),
            ]);
    }
}
