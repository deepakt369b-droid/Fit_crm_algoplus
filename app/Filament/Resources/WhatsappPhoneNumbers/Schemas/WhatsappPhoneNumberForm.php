<?php

namespace App\Filament\Resources\WhatsappPhoneNumbers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WhatsappPhoneNumberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('display_phone_number')
                    ->label(__('app.whatsapp.display_phone_number'))
                    ->required()
                    ->maxLength(32),
                TextInput::make('verified_name')
                    ->label(__('app.whatsapp.verified_name'))
                    ->maxLength(255),
                TextInput::make('waba_id')
                    ->label(__('app.whatsapp.waba_id'))
                    ->required()
                    ->maxLength(64),
                TextInput::make('phone_number_id')
                    ->label(__('app.whatsapp.phone_number_id'))
                    ->required()
                    ->maxLength(64)
                    ->unique(ignoreRecord: true),
                TextInput::make('access_token')
                    ->label(__('app.whatsapp.access_token'))
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(__('app.whatsapp.access_token_helper')),
                Toggle::make('is_shared')
                    ->label(__('app.whatsapp.is_shared'))
                    ->helperText(__('app.whatsapp.is_shared_helper')),
            ]);
    }
}
