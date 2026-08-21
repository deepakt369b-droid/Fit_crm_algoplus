<?php

namespace App\Filament\Resources\WhatsappPhoneNumbers\Tables;

use App\Models\WhatsappPhoneNumber;
use App\Services\WhatsApp\TemplateSyncer;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Http\Client\RequestException;

class WhatsappPhoneNumberTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_phone_number')
                    ->label(__('app.whatsapp.display_phone_number'))
                    ->searchable(),
                TextColumn::make('verified_name')
                    ->label(__('app.whatsapp.verified_name')),
                IconColumn::make('is_shared')
                    ->label(__('app.whatsapp.is_shared'))
                    ->boolean(),
                TextColumn::make('status')
                    ->label(__('app.fields.status'))
                    ->badge(),
                TextColumn::make('templates_count')
                    ->label(__('app.whatsapp.templates'))
                    ->counts('templates'),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->emptyStateHeading(__('app.empty.no_records', ['records' => __('app.resources.whatsapp_phone_numbers.plural')]))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('app.actions.new', ['resource' => __('app.resources.whatsapp_phone_numbers.singular')]))
                    ->modalWidth('lg')
                    ->createAnother(false),
            ])
            ->recordActions([
                Action::make('syncTemplates')
                    ->label(__('app.whatsapp.sync_templates'))
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (WhatsappPhoneNumber $record): void {
                        try {
                            $count = app(TemplateSyncer::class)->sync($record);

                            Notification::make()
                                ->title(__('app.whatsapp.templates_synced', ['count' => $count]))
                                ->success()
                                ->send();
                        } catch (RequestException $exception) {
                            Notification::make()
                                ->title(__('app.whatsapp.sync_failed'))
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                EditAction::make()->modalWidth('lg'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
