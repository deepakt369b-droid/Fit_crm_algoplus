<?php

namespace App\Filament\Resources\WhatsappContacts\Tables;

use App\Models\WhatsappContact;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WhatsappContactTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.fields.name'))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->label(__('app.fields.contact'))
                    ->searchable(),
                TextColumn::make('opt_in_status')
                    ->label(__('app.whatsapp.opt_in_status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'opted_in' => 'success',
                        'opted_out' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('source')
                    ->label(__('app.whatsapp.source'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_inbound_at')
                    ->label(__('app.whatsapp.last_inbound_at'))
                    ->since()
                    ->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('opt_in_status')
                    ->label(__('app.whatsapp.opt_in_status'))
                    ->options([
                        'unknown' => __('app.whatsapp.opt_in_statuses.unknown'),
                        'opted_in' => __('app.whatsapp.opt_in_statuses.opted_in'),
                        'opted_out' => __('app.whatsapp.opt_in_statuses.opted_out'),
                    ]),
            ])
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateHeading(__('app.empty.no_records', ['records' => __('app.resources.whatsapp_contacts.plural')]))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('app.actions.new', ['resource' => __('app.resources.whatsapp_contacts.singular')]))
                    ->modalWidth('md')
                    ->createAnother(false),
            ])
            ->recordActions([
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
