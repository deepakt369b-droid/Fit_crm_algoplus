<?php

namespace App\Filament\Resources\WhatsappKnowledgeBaseArticles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WhatsappKnowledgeBaseArticleTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('app.whatsapp.kb_title'))
                    ->searchable(),
                TextColumn::make('content')
                    ->label(__('app.whatsapp.kb_content'))
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('updated_at')
                    ->label(__('app.fields.updated_at'))
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('title')
            ->emptyStateIcon('heroicon-o-book-open')
            ->emptyStateHeading(__('app.empty.no_records', ['records' => __('app.resources.whatsapp_knowledge_base_articles.plural')]))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('app.actions.new', ['resource' => __('app.resources.whatsapp_knowledge_base_articles.singular')]))
                    ->modalWidth('lg')
                    ->createAnother(false),
            ])
            ->recordActions([
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
