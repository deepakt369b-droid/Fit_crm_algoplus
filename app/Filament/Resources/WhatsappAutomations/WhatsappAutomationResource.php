<?php

namespace App\Filament\Resources\WhatsappAutomations;

use App\Filament\Resources\WhatsappAutomations\Pages\ListWhatsappAutomations;
use App\Filament\Resources\WhatsappAutomations\Pages\ViewWhatsappAutomation;
use App\Filament\Resources\WhatsappAutomations\RelationManagers\RunsRelationManager;
use App\Filament\Resources\WhatsappAutomations\Schemas\WhatsappAutomationForm;
use App\Filament\Resources\WhatsappAutomations\Tables\WhatsappAutomationTable;
use App\Helpers\Helpers;
use App\Models\WhatsappAutomation;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class WhatsappAutomationResource extends Resource
{
    protected static ?string $model = WhatsappAutomation::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return Helpers::marketingFeatureEnabled('automations');
    }

    public static function getModelLabel(): string
    {
        return __('app.resources.whatsapp_automations.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.whatsapp_automations.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return WhatsappAutomationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsappAutomationTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RunsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsappAutomations::route('/'),
            'view' => ViewWhatsappAutomation::route('/{record}'),
        ];
    }
}
