<?php

namespace App\Filament\Resources\WhatsappContacts;

use App\Filament\Resources\WhatsappContacts\Pages\ListWhatsappContacts;
use App\Filament\Resources\WhatsappContacts\Schemas\WhatsappContactForm;
use App\Filament\Resources\WhatsappContacts\Tables\WhatsappContactTable;
use App\Helpers\Helpers;
use App\Models\WhatsappContact;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class WhatsappContactResource extends Resource
{
    protected static ?string $model = WhatsappContact::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return Helpers::marketingFeatureEnabled('inbox');
    }

    public static function getModelLabel(): string
    {
        return __('app.resources.whatsapp_contacts.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.whatsapp_contacts.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone'];
    }

    public static function form(Schema $schema): Schema
    {
        return WhatsappContactForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsappContactTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsappContacts::route('/'),
        ];
    }
}
