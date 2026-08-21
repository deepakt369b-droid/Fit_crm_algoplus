<?php

namespace App\Filament\Resources\WhatsappContacts\Schemas;

use App\Models\Member;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WhatsappContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label(__('app.fields.name'))
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label(__('app.fields.contact'))
                    ->required()
                    ->maxLength(32),
                Select::make('contactable_id')
                    ->label(__('app.whatsapp.link_to_member'))
                    ->options(fn (): array => Member::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->afterStateUpdated(fn (callable $set, $state) => $set('contactable_type', filled($state) ? Member::class : null))
                    ->dehydrated(fn ($state): bool => filled($state)),
                Select::make('opt_in_status')
                    ->label(__('app.whatsapp.opt_in_status'))
                    ->options([
                        'unknown' => __('app.whatsapp.opt_in_statuses.unknown'),
                        'opted_in' => __('app.whatsapp.opt_in_statuses.opted_in'),
                        'opted_out' => __('app.whatsapp.opt_in_statuses.opted_out'),
                    ])
                    ->required(),
            ]);
    }
}
