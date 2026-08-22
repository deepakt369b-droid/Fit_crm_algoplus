<?php

namespace App\Filament\Resources\WhatsappAutomations\Tables;

use App\Models\WhatsappAutomation;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WhatsappAutomationTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.fields.name'))
                    ->searchable(),
                TextColumn::make('trigger_type')
                    ->label(__('app.whatsapp.trigger'))
                    ->formatStateUsing(fn (string $state): string => __('app.whatsapp.triggers.'.$state)),
                TextColumn::make('phoneNumber.display_phone_number')
                    ->label(__('app.resources.whatsapp_phone_numbers.singular'))
                    ->placeholder(__('app.whatsapp.any_number')),
                TextColumn::make('status')
                    ->label(__('app.fields.status'))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('runs_count')
                    ->label(__('app.whatsapp.runs'))
                    ->counts('runs'),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateIcon('heroicon-o-bolt')
            ->emptyStateHeading(__('app.empty.no_records', ['records' => __('app.resources.whatsapp_automations.plural')]))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('app.actions.new', ['resource' => __('app.resources.whatsapp_automations.singular')]))
                    ->modalWidth('4xl')
                    ->createAnother(false),
            ])
            ->recordActions([
                Action::make('toggleStatus')
                    ->label(fn (WhatsappAutomation $record): string => $record->status === 'active'
                        ? __('app.whatsapp.pause')
                        : __('app.whatsapp.activate'))
                    ->icon(fn (WhatsappAutomation $record): string => $record->status === 'active' ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->action(function (WhatsappAutomation $record): void {
                        $record->forceFill(['status' => $record->status === 'active' ? 'inactive' : 'active'])->save();

                        Notification::make()
                            ->title(__('app.whatsapp.status_updated'))
                            ->success()
                            ->send();
                    }),
                ViewAction::make()->modalWidth('4xl'),
                EditAction::make()->modalWidth('4xl'),
                DeleteAction::make(),
            ]);
    }
}
