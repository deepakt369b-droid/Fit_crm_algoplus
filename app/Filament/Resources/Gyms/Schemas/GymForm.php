<?php

namespace App\Filament\Resources\Gyms\Schemas;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class GymForm
{
    /**
     * Configure the gym (branch) form schema.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('app.fields.name'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $state, callable $set, ?string $old, string $operation): void {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label(__('app.fields.slug'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText(__('app.placeholders.gym_slug_helper')),
                        TextInput::make('code')
                            ->label(__('app.fields.code'))
                            ->maxLength(50),
                        Select::make('status')
                            ->label(__('app.fields.status'))
                            ->options([
                                'active' => __('app.status.active'),
                                'inactive' => __('app.status.inactive'),
                            ])
                            ->required()
                            ->default('active'),
                        TextInput::make('email')
                            ->label(__('app.fields.email'))
                            ->email()
                            ->maxLength(255),
                        TextInput::make('contact')
                            ->label(__('app.fields.contact'))
                            ->tel()
                            ->maxLength(50),
                        TextInput::make('timezone')
                            ->label(__('app.fields.timezone'))
                            ->required()
                            ->default('UTC')
                            ->maxLength(64),
                        TextInput::make('currency')
                            ->label(__('app.fields.currency'))
                            ->required()
                            ->default('USD')
                            ->maxLength(3),
                    ]),
                Section::make(__('app.fields.address'))
                    ->columns(2)
                    ->schema([
                        Grid::make(1)
                            ->columnSpan(2)
                            ->schema([
                                TextInput::make('address')
                                    ->label(__('app.fields.address'))
                                    ->maxLength(255),
                            ]),
                        TextInput::make('country')
                            ->label(__('app.fields.country'))
                            ->maxLength(255),
                        TextInput::make('state')
                            ->label(__('app.fields.state'))
                            ->maxLength(255),
                        TextInput::make('city')
                            ->label(__('app.fields.city'))
                            ->maxLength(255),
                        TextInput::make('pincode')
                            ->label(__('app.fields.pincode'))
                            ->maxLength(20),
                    ]),
            ]);
    }
}
