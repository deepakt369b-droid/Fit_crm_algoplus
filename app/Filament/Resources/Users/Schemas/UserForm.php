<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Forms\Components\CameraCapture;
use App\Helpers\Helpers;
use App\Models\Gym;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class UserForm
{
    /**
     * Configure the user form schema.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(4)
                    ->schema([
                        CameraCapture::make('photo')
                            ->label(__('app.fields.photo'))
                            ->disk('public')
                            ->directory('images')
                            ->required(),
                        Grid::make()
                            ->columns(3)
                            ->schema([
                                Group::make()
                                    ->schema([
                                        TextInput::make('name')
                                            ->label(__('app.fields.name'))
                                            ->required()
                                            ->placeholder(__('app.placeholders.example_full_name')),
                                        TextInput::make('email')
                                            ->label(__('app.fields.email'))
                                            ->email()
                                            ->required()
                                            ->placeholder(__('app.placeholders.example_email'))
                                            ->unique(ignorable: fn ($record) => $record)
                                            ->prefixIcon('heroicon-m-envelope'),
                                    ])->columns(2)->columnSpanFull(),
                                TextInput::make('contact')
                                    ->label(__('app.fields.contact'))
                                    ->prefixIcon('heroicon-m-phone')
                                    ->tel()
                                    ->maxLength(20)
                                    ->regex('/^\+?[0-9\s\-\(\)]+$/') // Allows +, digits, spaces, dashes, and parentheses
                                    ->required(),
                                Select::make('gender')
                                    ->label(__('app.fields.gender'))
                                    ->options([
                                        'male' => __('app.options.gender.male'),
                                        'female' => __('app.options.gender.female'),
                                        'other' => __('app.options.gender.other'),
                                    ])
                                    ->required()
                                    ->default('male')
                                    ->selectablePlaceholder(false),
                                DatePicker::make('dob')
                                    ->required()
                                    ->label(__('app.fields.dob')),
                                Select::make('role')
                                    ->label(__('app.fields.role'))
                                    ->relationship('roles', 'name')
                                    ->getOptionLabelFromRecordUsing(
                                        fn ($record): string => Str::headline($record->name)
                                    ),
                                Select::make('gym_id')
                                    ->label(__('app.resources.gyms.singular'))
                                    ->options(fn (): array => Gym::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->required()
                                    // Only relevant when there is no ambient branch (the superadmin
                                    // panel, which has no tenant): inside a branch's own admin panel,
                                    // BelongsToGym auto-fills gym_id from the current tenant instead.
                                    ->visible(fn (): bool => Filament::getTenant() === null),
                                TextInput::make('password')
                                    ->label(__('app.fields.password'))
                                    ->password()
                                    ->hiddenOn(['view'])
                                    ->dehydrated(fn ($state) => filled($state))
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->revealable(),
                                TextInput::make('password_confirmation')
                                    ->label(__('app.fields.password_confirmation'))
                                    ->password()
                                    ->hiddenOn(['view'])
                                    ->revealable()
                                    ->required(fn (callable $get): bool => filled($get('password')))
                                    ->same('password'),
                            ])
                            ->columnSpan(3),
                    ]),
                Section::make(__('app.ui.location'))
                    ->schema([
                        Textarea::make('address')
                            ->label(__('app.fields.address'))
                            ->required()
                            ->placeholder(__('app.placeholders.address_example')),
                        Group::make()
                            ->schema([
                                Select::make('country')
                                    ->label(__('app.fields.country'))
                                    ->placeholder(__('app.placeholders.select_country'))
                                    ->options(Helpers::getCountries())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(fn (callable $set) => [
                                        $set('state', null),
                                        $set('city', null),
                                    ]),
                                Select::make('state')
                                    ->label(__('app.fields.state'))
                                    ->placeholder(__('app.placeholders.select_state'))
                                    ->options(fn ($get) => Helpers::getStates($get('country')))
                                    ->searchable()
                                    ->reactive(),
                                Select::make('city')
                                    ->label(__('app.fields.city'))
                                    ->placeholder(__('app.placeholders.select_city'))
                                    ->options(fn ($get) => Helpers::getCities($get('state')))
                                    ->searchable()
                                    ->reactive(),
                                TextInput::make('pincode')
                                    ->label(__('app.fields.pincode'))
                                    ->placeholder(__('app.placeholders.pincode')),
                            ])->columns(4),
                    ]),
            ]);
    }
}
