<?php

namespace App\Filament\Resources\WhatsappBroadcasts\Schemas;

use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappTemplate;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WhatsappBroadcastForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label(__('app.fields.name'))
                    ->required()
                    ->maxLength(255),
                Select::make('wa_phone_number_id')
                    ->label(__('app.resources.whatsapp_phone_numbers.singular'))
                    ->options(fn (): array => WhatsappPhoneNumber::query()
                        ->where('status', 'active')
                        ->pluck('display_phone_number', 'id')
                        ->all())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('wa_template_id', null)),
                Select::make('wa_template_id')
                    ->label(__('app.resources.whatsapp_templates.singular'))
                    ->options(function (callable $get): array {
                        $phoneNumberId = $get('wa_phone_number_id');

                        if (blank($phoneNumberId)) {
                            return [];
                        }

                        return WhatsappTemplate::query()
                            ->where('wa_phone_number_id', $phoneNumberId)
                            ->where('status', 'approved')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->required()
                    ->helperText(__('app.whatsapp.only_approved_templates_hint')),
            ]);
    }
}
