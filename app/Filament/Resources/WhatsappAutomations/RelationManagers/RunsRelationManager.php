<?php

namespace App\Filament\Resources\WhatsappAutomations\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only: runs are created by AutomationTriggerService, not by hand.
 */
class RunsRelationManager extends RelationManager
{
    protected static string $relationship = 'runs';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('app.whatsapp.runs');
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
                        'completed' => 'success',
                        'failed' => 'danger',
                        'waiting' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('current_step_index')
                    ->label(__('app.whatsapp.current_step')),
                TextColumn::make('error_message')
                    ->label(__('app.whatsapp.error'))
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('resume_at')
                    ->label(__('app.whatsapp.resumes_at'))
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
