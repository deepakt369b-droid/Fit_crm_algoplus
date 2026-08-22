<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A short reference article the AI reply assistant may draw on when
 * drafting a suggested WhatsApp reply (see AiReplyAssistant).
 *
 * @property int $id
 * @property int|null $gym_id
 * @property string $title
 * @property string $content
 */
class WhatsappKnowledgeBaseArticle extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappKnowledgeBaseArticleFactory> */
    use BelongsToGym, HasFactory;

    protected $table = 'wa_knowledge_base_articles';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'gym_id',
        'title',
        'content',
    ];
}
