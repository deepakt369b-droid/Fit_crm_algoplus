<?php

namespace App\Filament\Resources\Devices\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DeviceForm
{
    /**
     * Configure the device form schema.
     *
     * Only the fields an admin sets when naming a door — pairing itself
     * happens afterwards through the "Pair device" action.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label(__('app.fields.name'))
                    ->placeholder(__('app.placeholders.device_name'))
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label(__('app.fields.device_type'))
                    ->options([
                        'face' => __('app.options.device_type.face'),
                        'fingerprint' => __('app.options.device_type.fingerprint'),
                        'hybrid' => __('app.options.device_type.hybrid'),
                    ])
                    ->required()
                    ->default('face')
                    ->selectablePlaceholder(false),
                TextInput::make('location')
                    ->label(__('app.fields.location'))
                    ->placeholder(__('app.placeholders.device_location'))
                    ->maxLength(255),
            ]);
    }
}
