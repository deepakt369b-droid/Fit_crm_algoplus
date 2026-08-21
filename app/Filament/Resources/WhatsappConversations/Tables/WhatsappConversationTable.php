<?php

namespace App\Filament\Resources\WhatsappConversations\Tables;

use App\Filament\Resources\WhatsappConversations\WhatsappConversationResource;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WhatsappConversationTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('20s')
            ->columns([
                TextColumn::make('contact.name')
                    ->label(__('app.fields.name'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('contact.phone')
                    ->label(__('app.fields.contact'))
                    ->searchable(),
                TextColumn::make('unread_count')
                    ->label(__('app.whatsapp.unread'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('status')
                    ->label(__('app.fields.status'))
                    ->badge(),
                TextColumn::make('assignedUser.name')
                    ->label(__('app.whatsapp.assigned_to'))
                    ->placeholder('—'),
                TextColumn::make('last_message_at')
                    ->label(__('app.whatsapp.last_message_at'))
                    ->since()
                    ->placeholder('—'),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => __('app.whatsapp.conversation_statuses.open'),
                        'closed' => __('app.whatsapp.conversation_statuses.closed'),
                    ]),
            ])
            ->recordUrl(fn ($record): string => WhatsappConversationResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                Action::make('open')
                    ->label(__('app.whatsapp.open_conversation'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url(fn ($record): string => WhatsappConversationResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateIcon('heroicon-o-inbox')
            ->emptyStateHeading(__('app.empty.no_records', ['records' => __('app.resources.whatsapp_conversations.plural')]));
    }
}
