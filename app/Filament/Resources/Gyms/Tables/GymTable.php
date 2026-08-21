<?php

namespace App\Filament\Resources\Gyms\Tables;

use App\Models\Gym;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GymTable
{
    /**
     * Configure the gym (branch) table schema.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->searchable()
                    ->label(__('app.fields.name'))
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->label(__('app.fields.slug')),
                TextColumn::make('code')
                    ->searchable()
                    ->label(__('app.fields.code')),
                TextColumn::make('status')
                    ->badge()
                    ->label(__('app.fields.status')),
                TextColumn::make('city')
                    ->label(__('app.fields.city'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->searchable()
                    ->date('d-m-Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->emptyStateHeading(__('app.empty.no_records', ['records' => __('app.resources.gyms.plural')]))
            ->emptyStateDescription(__('app.empty.create_to_get_started', ['resource' => __('app.resources.gyms.singular')]))
            ->emptyStateActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->label(__('app.actions.new', ['resource' => __('app.resources.gyms.singular')]))
                    ->modalHeading(__('app.actions.new', ['resource' => __('app.resources.gyms.singular')]))
                    ->modalWidth('lg')
                    ->createAnother(false)
                    ->hidden(fn (): bool => Gym::exists()),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalCancelAction(false)
                    ->modalWidth('lg'),
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
