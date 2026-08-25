<?php

namespace App\Filament\Resources\WhatsappBroadcasts;

use App\Filament\Resources\WhatsappBroadcasts\Pages\ListWhatsappBroadcasts;
use App\Filament\Resources\WhatsappBroadcasts\Pages\ViewWhatsappBroadcast;
use App\Filament\Resources\WhatsappBroadcasts\RelationManagers\RecipientsRelationManager;
use App\Filament\Resources\WhatsappBroadcasts\Schemas\WhatsappBroadcastForm;
use App\Filament\Resources\WhatsappBroadcasts\Tables\WhatsappBroadcastTable;
use App\Helpers\Helpers;
use App\Models\WhatsappBroadcast;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class WhatsappBroadcastResource extends Resource
{
    protected static ?string $model = WhatsappBroadcast::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        $enabled = Helpers::marketingFeatureEnabled('broadcasts');

        // TEMPORARY diagnostic — remove after the Forbidden investigation.
        error_log('WBC-canAccess ' . json_encode([
            'enabled' => $enabled,
            'gym_id' => app(\App\Contracts\TenantContext::class)->gymId(),
            'marketing' => app(\App\Contracts\SettingsRepository::class)->get()['marketing'] ?? null,
        ]));

        return $enabled;
    }

    public static function getModelLabel(): string
    {
        return __('app.resources.whatsapp_broadcasts.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.whatsapp_broadcasts.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return WhatsappBroadcastForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsappBroadcastTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RecipientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsappBroadcasts::route('/'),
            'view' => ViewWhatsappBroadcast::route('/{record}'),
        ];
    }
}
