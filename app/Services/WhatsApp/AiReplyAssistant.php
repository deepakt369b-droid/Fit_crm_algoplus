<?php

namespace App\Services\WhatsApp;

use App\Contracts\AnthropicMessagesClient;
use App\Helpers\Helpers;
use App\Models\WhatsappAiSetting;
use App\Models\WhatsappConversation;
use App\Models\WhatsappKnowledgeBaseArticle;
use App\Models\WhatsappMessage;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Drafts a suggested WhatsApp reply from a conversation's message history
 * plus (if the knowledge_base feature is enabled) the branch's knowledge
 * base articles. Never sends anything itself - the Filament action that
 * calls this only pre-fills the reply composer; staff review and send.
 */
class AiReplyAssistant
{
    public const DEFAULT_MODEL = 'claude-opus-5';

    private const MAX_HISTORY_MESSAGES = 20;

    private const MAX_TOKENS = 1024;

    private const MAX_KNOWLEDGE_BASE_ARTICLES = 20;

    public function __construct(private readonly AnthropicMessagesClient $client) {}

    public function suggestReply(WhatsappConversation $conversation): string
    {
        $settings = WhatsappAiSetting::query()->first();

        if ($settings === null || blank($settings->anthropic_api_key)) {
            throw new RuntimeException(__('app.whatsapp.ai_not_configured'));
        }

        $messages = $this->buildHistory($conversation);

        if ($messages === []) {
            throw new RuntimeException(__('app.whatsapp.ai_needs_inbound_message'));
        }

        return $this->client->complete(
            apiKey: (string) $settings->anthropic_api_key,
            model: filled($settings->model) ? $settings->model : self::DEFAULT_MODEL,
            system: $this->buildSystemPrompt($settings),
            messages: $messages,
            maxTokens: self::MAX_TOKENS,
        );
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function buildHistory(WhatsappConversation $conversation): array
    {
        /** @var Collection<int, WhatsappMessage> $messages */
        $messages = $conversation->messages()
            ->whereNotNull('body')
            ->where('body', '!=', '')
            ->latest('occurred_at')
            ->limit(self::MAX_HISTORY_MESSAGES)
            ->get()
            ->reverse()
            ->values();

        // The Messages API requires the first message to have role
        // "user" - trim any leading outbound (staff-initiated) messages
        // so a conversation with no inbound message yet doesn't 400.
        // 'in'/'out' match WhatsappMessage::direction as written by
        // InboundWebhookProcessor/OutboundMessageSender, not the more
        // readable 'inbound'/'outbound' one might expect.
        $firstInboundIndex = $messages->search(
            fn (WhatsappMessage $message): bool => $message->direction === 'in'
        );

        if ($firstInboundIndex === false) {
            return [];
        }

        return $messages->slice($firstInboundIndex)
            ->map(fn (WhatsappMessage $message): array => [
                'role' => $message->direction === 'in' ? 'user' : 'assistant',
                'content' => (string) $message->body,
            ])
            ->values()
            ->all();
    }

    private function buildSystemPrompt(WhatsappAiSetting $settings): string
    {
        $parts = [
            'You are drafting a WhatsApp reply on behalf of gym staff. '
            .'Write a short, friendly, ready-to-send reply in the same language as the customer. '
            .'Do not invent information you have not been given.',
        ];

        if (filled($settings->system_prompt)) {
            $parts[] = (string) $settings->system_prompt;
        }

        $knowledgeBase = $this->knowledgeBaseContext();

        if ($knowledgeBase !== '') {
            $parts[] = "Reference knowledge base (use only what's relevant):\n{$knowledgeBase}";
        }

        return implode("\n\n", $parts);
    }

    private function knowledgeBaseContext(): string
    {
        if (! Helpers::marketingFeatureEnabled('knowledge_base')) {
            return '';
        }

        return WhatsappKnowledgeBaseArticle::query()
            ->orderBy('title')
            ->limit(self::MAX_KNOWLEDGE_BASE_ARTICLES)
            ->get()
            ->map(fn (WhatsappKnowledgeBaseArticle $article): string => "### {$article->title}\n{$article->content}")
            ->implode("\n\n");
    }
}
