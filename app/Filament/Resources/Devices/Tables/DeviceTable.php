<?php

namespace App\Filament\Resources\Devices\Tables;

use App\Models\Device;
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

class DeviceTable
{
    /**
     * Configure the device table schema.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('app.fields.device_type'))
                    ->badge(),
                TextColumn::make('location')
                    ->label(__('app.fields.location'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(__('app.fields.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paired' => 'success',
                        'revoked' => 'danger',
                        default => 'warning',
                    }),
                IconColumn::make('online')
                    ->label(__('app.devices.online'))
                    ->boolean()
                    ->getStateUsing(fn (Device $record): bool => $record->isOnline()),
                TextColumn::make('last_seen_at')
                    ->label(__('app.devices.last_check_in'))
                    ->since()
                    ->placeholder('—'),
                TextColumn::make('firmware_version')
                    ->label(__('app.devices.firmware'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateIcon('heroicon-o-qr-code')
            ->emptyStateHeading(__('app.empty.no_records', ['records' => __('app.resources.devices.plural')]))
            ->emptyStateDescription(__('app.empty.create_to_get_started', ['resource' => __('app.resources.devices.singular')]))
            ->emptyStateActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->label(__('app.actions.new', ['resource' => __('app.resources.devices.singular')]))
                    ->modalHeading(__('app.actions.new', ['resource' => __('app.resources.devices.singular')]))
                    ->modalWidth('md')
                    ->createAnother(false),
            ])
            ->recordActions([
                Action::make('pairingCode')
                    ->label(fn (Device $record): string => $record->status === 'paired'
                        ? __('app.devices.view_status')
                        : __('app.devices.pair_device'))
                    ->icon('heroicon-o-qr-code')
                    ->modalHeading(__('app.devices.pair_device'))
                    ->modalContent(function (Device $record): \Illuminate\Contracts\View\View {
                        $code = null;

                        if (
                            $record->status !== 'paired'
                            && ($record->pairing_expires_at === null || $record->pairing_expires_at->isPast())
                        ) {
                            $code = $record->generatePairingCode();
                        }

                        return view('filament.devices.pairing-modal', ['device' => $record->refresh(), 'code' => $code]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('app.actions.close')),
                Action::make('regenerateCode')
                    ->label(__('app.devices.regenerate_code'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Device $record): bool => $record->status !== 'paired')
                    ->requiresConfirmation()
                    ->action(function (Device $record): void {
                        $record->generatePairingCode();

                        Notification::make()
                            ->title(__('app.devices.code_regenerated'))
                            ->success()
                            ->send();
                    }),
                Action::make('revoke')
                    ->label(__('app.devices.revoke'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (Device $record): bool => $record->status === 'paired')
                    ->requiresConfirmation()
                    ->modalDescription(__('app.devices.revoke_confirmation'))
                    ->action(function (Device $record): void {
                        $record->tokens()->delete();
                        $record->forceFill([
                            'status' => 'revoked',
                            'paired_at' => null,
                            'last_seen_at' => null,
                        ])->save();

                        Notification::make()
                            ->title(__('app.devices.revoked'))
                            ->success()
                            ->send();
                    }),
                EditAction::make()->modalWidth('md'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
