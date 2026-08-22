<?php

namespace App\Filament\Resources\WhatsappKnowledgeBaseArticles;

use App\Filament\Resources\WhatsappKnowledgeBaseArticles\Pages\ListWhatsappKnowledgeBaseArticles;
use App\Filament\Resources\WhatsappKnowledgeBaseArticles\Schemas\WhatsappKnowledgeBaseArticleForm;
use App\Filament\Resources\WhatsappKnowledgeBaseArticles\Tables\WhatsappKnowledgeBaseArticleTable;
use App\Helpers\Helpers;
use App\Models\WhatsappKnowledgeBaseArticle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class WhatsappKnowledgeBaseArticleResource extends Resource
{
    protected static ?string $model = WhatsappKnowledgeBaseArticle::class;

    protected static ?string $recordTitleAttribute = 'title';

    public static function canAccess(): bool
    {
        return Helpers::marketingFeatureEnabled('knowledge_base');
    }

    public static function getModelLabel(): string
    {
        return __('app.resources.whatsapp_knowledge_base_articles.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.whatsapp_knowledge_base_articles.plural');
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title'];
    }

    public static function form(Schema $schema): Schema
    {
        return WhatsappKnowledgeBaseArticleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsappKnowledgeBaseArticleTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsappKnowledgeBaseArticles::route('/'),
        ];
    }
}
