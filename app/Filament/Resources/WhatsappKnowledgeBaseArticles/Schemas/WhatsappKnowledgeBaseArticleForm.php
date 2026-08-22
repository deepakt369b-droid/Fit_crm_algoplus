<?php

namespace App\Filament\Resources\WhatsappKnowledgeBaseArticles\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WhatsappKnowledgeBaseArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('title')
                    ->label(__('app.whatsapp.kb_title'))
                    ->required()
                    ->maxLength(255),
                Textarea::make('content')
                    ->label(__('app.whatsapp.kb_content'))
                    ->required()
                    ->rows(8)
                    ->helperText(__('app.whatsapp.kb_content_helper')),
            ]);
    }
}
