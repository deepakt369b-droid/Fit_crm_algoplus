<?php

namespace App\Filament\Resources\WhatsappBroadcasts\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Read-only: recipients are created by BroadcastDispatcher, not by hand.
 */
class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('app.resources.whatsapp_contacts.plural');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contact.name')
                    ->label(__('app.fields.name'))
                    ->placeholder('—'),
                TextColumn::make('contact.phone')
                    ->label(__('app.fields.contact')),
                TextColumn::make('status')
                    ->label(__('app.fields.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent', 'delivered', 'read' => 'success',
                        'failed' => 'danger',
                        'skipped', 'throttled' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('error_message')
                    ->label(__('app.whatsapp.error'))
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => __('app.whatsapp.message_statuses.queued'),
                        'sent' => __('app.whatsapp.message_statuses.sent'),
                        'delivered' => __('app.whatsapp.message_statuses.delivered'),
                        'read' => __('app.whatsapp.message_statuses.read'),
                        'failed' => __('app.whatsapp.message_statuses.failed'),
                    ]),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
