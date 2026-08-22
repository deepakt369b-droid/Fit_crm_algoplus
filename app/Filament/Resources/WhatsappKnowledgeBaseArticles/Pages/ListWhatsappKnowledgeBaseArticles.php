<?php

namespace App\Filament\Resources\WhatsappKnowledgeBaseArticles\Pages;

use App\Filament\Resources\WhatsappKnowledgeBaseArticles\WhatsappKnowledgeBaseArticleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWhatsappKnowledgeBaseArticles extends ListRecords
{
    protected static string $resource = WhatsappKnowledgeBaseArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('app.actions.new', ['resource' => WhatsappKnowledgeBaseArticleResource::getModelLabel()]))
                ->modalWidth('lg')
                ->createAnother(false),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.marketing'),
            WhatsappKnowledgeBaseArticleResource::getNavigationLabel(),
        ];
    }
}
