<?php

namespace App\Filament\Resources\WhatsappPhoneNumbers;

use App\Filament\Resources\WhatsappPhoneNumbers\Pages\ListWhatsappPhoneNumbers;
use App\Filament\Resources\WhatsappPhoneNumbers\Schemas\WhatsappPhoneNumberForm;
use App\Filament\Resources\WhatsappPhoneNumbers\Tables\WhatsappPhoneNumberTable;
use App\Helpers\Helpers;
use App\Models\WhatsappPhoneNumber;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class WhatsappPhoneNumberResource extends Resource
{
    protected static ?string $model = WhatsappPhoneNumber::class;

    protected static ?string $recordTitleAttribute = 'display_phone_number';

    public static function canAccess(): bool
    {
        return Helpers::marketingFeatureEnabled('inbox');
    }

    public static function getModelLabel(): string
    {
        return __('app.resources.whatsapp_phone_numbers.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.whatsapp_phone_numbers.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return WhatsappPhoneNumberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsappPhoneNumberTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsappPhoneNumbers::route('/'),
        ];
    }
}
