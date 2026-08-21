<?php

namespace App\Filament\Resources\WhatsappTemplates;

use App\Filament\Resources\WhatsappTemplates\Pages\ListWhatsappTemplates;
use App\Filament\Resources\WhatsappTemplates\Schemas\WhatsappTemplateInfolist;
use App\Filament\Resources\WhatsappTemplates\Tables\WhatsappTemplateTable;
use App\Helpers\Helpers;
use App\Models\WhatsappTemplate;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Read-only: templates are synced from Meta (see TemplateSyncer), not
 * authored here.
 */
class WhatsappTemplateResource extends Resource
{
    protected static ?string $model = WhatsappTemplate::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return Helpers::marketingFeatureEnabled('inbox');
    }

    public static function getModelLabel(): string
    {
        return __('app.resources.whatsapp_templates.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.whatsapp_templates.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return WhatsappTemplateTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WhatsappTemplateInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsappTemplates::route('/'),
        ];
    }
}
