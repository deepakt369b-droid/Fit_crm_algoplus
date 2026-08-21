<?php

namespace App\Filament\Resources\WhatsappConversations\Schemas;

use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Schema;

class WhatsappConversationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                ViewEntry::make('messages')
                    ->label('')
                    ->view('filament.resources.whatsapp-conversations.thread'),
            ]);
    }
}
