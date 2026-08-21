<?php

namespace App\Filament\Resources\WhatsappTemplates\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WhatsappTemplateTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.fields.name'))
                    ->searchable(),
                TextColumn::make('language')
                    ->label(__('app.whatsapp.language')),
                TextColumn::make('category')
                    ->label(__('app.whatsapp.category')),
                TextColumn::make('status')
                    ->label(__('app.fields.status'))
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'approved' => 'success',
                        'rejected', 'disabled' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('phoneNumber.display_phone_number')
                    ->label(__('app.resources.whatsapp_phone_numbers.singular')),
                TextColumn::make('synced_at')
                    ->label(__('app.whatsapp.synced_at'))
                    ->since()
                    ->placeholder('—'),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('app.fields.status'))
                    ->options([
                        'approved' => __('app.whatsapp.template_statuses.approved'),
                        'pending' => __('app.whatsapp.template_statuses.pending'),
                        'rejected' => __('app.whatsapp.template_statuses.rejected'),
                    ]),
            ])
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateHeading(__('app.empty.no_records', ['records' => __('app.resources.whatsapp_templates.plural')]))
            ->emptyStateDescription(__('app.whatsapp.sync_templates_hint'))
            ->recordActions([
                ViewAction::make()->modalWidth('lg'),
                DeleteAction::make(),
            ]);
    }
}
