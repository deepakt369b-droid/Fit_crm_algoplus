<?php

namespace App\Filament\Resources\WhatsappAutomations\Schemas;

use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappTag;
use App\Models\WhatsappTemplate;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WhatsappAutomationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label(__('app.fields.name'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('trigger_type')
                    ->label(__('app.whatsapp.trigger'))
                    ->options([
                        'contact_created' => __('app.whatsapp.triggers.contact_created'),
                        'keyword_received' => __('app.whatsapp.triggers.keyword_received'),
                        'opted_in' => __('app.whatsapp.triggers.opted_in'),
                    ])
                    ->live()
                    ->required(),
                TextInput::make('trigger_config.keyword')
                    ->label(__('app.whatsapp.trigger_keyword'))
                    ->visible(fn (callable $get): bool => $get('trigger_type') === 'keyword_received')
                    ->required(fn (callable $get): bool => $get('trigger_type') === 'keyword_received'),
                Select::make('wa_phone_number_id')
                    ->label(__('app.whatsapp.applies_to_number'))
                    ->options(fn (): array => WhatsappPhoneNumber::query()->pluck('display_phone_number', 'id')->all())
                    ->placeholder(__('app.whatsapp.any_number'))
                    ->columnSpanFull(),
                Repeater::make('steps')
                    ->label(__('app.whatsapp.steps'))
                    ->columnSpanFull()
                    ->addActionLabel(__('app.whatsapp.add_step'))
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): string => match ($state['type'] ?? null) {
                        'send_template' => __('app.whatsapp.step_types.send_template'),
                        'add_tag' => __('app.whatsapp.step_types.add_tag'),
                        'remove_tag' => __('app.whatsapp.step_types.remove_tag'),
                        'wait' => __('app.whatsapp.step_types.wait'),
                        'condition' => __('app.whatsapp.step_types.condition'),
                        'webhook' => __('app.whatsapp.step_types.webhook'),
                        default => __('app.whatsapp.new_step'),
                    })
                    ->schema([
                        Select::make('type')
                            ->label(__('app.whatsapp.step_type'))
                            ->options([
                                'send_template' => __('app.whatsapp.step_types.send_template'),
                                'add_tag' => __('app.whatsapp.step_types.add_tag'),
                                'remove_tag' => __('app.whatsapp.step_types.remove_tag'),
                                'wait' => __('app.whatsapp.step_types.wait'),
                                'condition' => __('app.whatsapp.step_types.condition'),
                                'webhook' => __('app.whatsapp.step_types.webhook'),
                            ])
                            ->live()
                            ->required(),
                        Select::make('template_id')
                            ->label(__('app.resources.whatsapp_templates.singular'))
                            ->options(fn (): array => WhatsappTemplate::query()->where('status', 'approved')->pluck('name', 'id')->all())
                            ->visible(fn (callable $get): bool => $get('type') === 'send_template')
                            ->required(fn (callable $get): bool => $get('type') === 'send_template'),
                        Select::make('tag_id')
                            ->label(__('app.whatsapp.tag'))
                            ->options(fn (): array => WhatsappTag::query()->pluck('name', 'id')->all())
                            ->visible(fn (callable $get): bool => in_array($get('type'), ['add_tag', 'remove_tag'], true))
                            ->required(fn (callable $get): bool => in_array($get('type'), ['add_tag', 'remove_tag'], true)),
                        TextInput::make('minutes')
                            ->label(__('app.whatsapp.wait_minutes'))
                            ->numeric()
                            ->minValue(1)
                            ->visible(fn (callable $get): bool => $get('type') === 'wait')
                            ->required(fn (callable $get): bool => $get('type') === 'wait'),
                        TextInput::make('field')
                            ->label(__('app.whatsapp.condition_field'))
                            ->placeholder('opt_in_status')
                            ->visible(fn (callable $get): bool => $get('type') === 'condition')
                            ->required(fn (callable $get): bool => $get('type') === 'condition'),
                        Select::make('operator')
                            ->label(__('app.whatsapp.condition_operator'))
                            ->options([
                                'equals' => __('app.whatsapp.operators.equals'),
                                'not_equals' => __('app.whatsapp.operators.not_equals'),
                                'has_tag' => __('app.whatsapp.operators.has_tag'),
                            ])
                            ->visible(fn (callable $get): bool => $get('type') === 'condition'),
                        TextInput::make('value')
                            ->label(__('app.whatsapp.condition_value'))
                            ->visible(fn (callable $get): bool => $get('type') === 'condition'),
                        TextInput::make('true_step')
                            ->label(__('app.whatsapp.true_step'))
                            ->numeric()
                            ->helperText(__('app.whatsapp.step_index_hint'))
                            ->visible(fn (callable $get): bool => $get('type') === 'condition'),
                        TextInput::make('false_step')
                            ->label(__('app.whatsapp.false_step'))
                            ->numeric()
                            ->helperText(__('app.whatsapp.step_index_hint'))
                            ->visible(fn (callable $get): bool => $get('type') === 'condition'),
                        TextInput::make('url')
                            ->label(__('app.whatsapp.webhook_url'))
                            ->url()
                            ->visible(fn (callable $get): bool => $get('type') === 'webhook')
                            ->required(fn (callable $get): bool => $get('type') === 'webhook'),
                        Select::make('method')
                            ->label(__('app.whatsapp.webhook_method'))
                            ->options(['POST' => 'POST', 'GET' => 'GET', 'PUT' => 'PUT'])
                            ->default('POST')
                            ->visible(fn (callable $get): bool => $get('type') === 'webhook'),
                    ])
                    ->columns(2),
            ]);
    }
}
