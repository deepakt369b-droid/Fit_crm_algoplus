<?php

namespace App\Filament\Resources\WhatsappConversations;

use App\Filament\Resources\WhatsappConversations\Pages\ListWhatsappConversations;
use App\Filament\Resources\WhatsappConversations\Pages\ViewWhatsappConversation;
use App\Filament\Resources\WhatsappConversations\Tables\WhatsappConversationTable;
use App\Helpers\Helpers;
use App\Models\WhatsappConversation;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * The shared inbox: every branch conversation across every connected
 * WhatsApp number, assignable to staff.
 */
class WhatsappConversationResource extends Resource
{
    protected static ?string $model = WhatsappConversation::class;

    public static function canAccess(): bool
    {
        return Helpers::marketingFeatureEnabled('inbox');
    }

    public static function getModelLabel(): string
    {
        return __('app.resources.whatsapp_conversations.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.whatsapp_conversations.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('app.whatsapp.inbox');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->where('unread_count', '>', 0)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return WhatsappConversationTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsappConversations::route('/'),
            'view' => ViewWhatsappConversation::route('/{record}'),
        ];
    }
}
