<?php

use App\Contracts\AnthropicMessagesClient;
use App\Helpers\Helpers;
use App\Models\Gym;
use App\Models\WhatsappAiSetting;
use App\Models\WhatsappConversation;
use App\Models\WhatsappKnowledgeBaseArticle;
use App\Services\WhatsApp\AiReplyAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Helpers::setTestSettingsOverride(null);
});

/**
 * Binds an AnthropicMessagesClient that either returns a canned reply or
 * (when $reply is null) throws, simulating an upstream API failure. Lets
 * these tests exercise the real guard/history-building logic in
 * AiReplyAssistant without touching the actual SDK.
 */
function bindFakeAnthropicClient(?string $reply): void
{
    app()->bind(AnthropicMessagesClient::class, function () use ($reply) {
        return new class($reply) implements AnthropicMessagesClient
        {
            public function __construct(private ?string $reply) {}

            public function complete(string $apiKey, string $model, string $system, array $messages, int $maxTokens): string
            {
                if ($this->reply === null) {
                    throw new RuntimeException('Anthropic API error: simulated failure');
                }

                return $this->reply;
            }
        };
    });
}

it('throws when no AI settings exist for the branch', function (): void {
    $gym = Gym::factory()->create();
    $conversation = WhatsappConversation::factory()->for($gym)->create();

    expect(fn () => app(AiReplyAssistant::class)->suggestReply($conversation))
        ->toThrow(RuntimeException::class);
});

it('throws when the conversation has no inbound message yet', function (): void {
    $gym = Gym::factory()->create();
    WhatsappAiSetting::factory()->for($gym)->create();
    $conversation = WhatsappConversation::factory()->for($gym)->create();
    $conversation->messages()->create([
        'gym_id' => $gym->id,
        'direction' => 'out',
        'type' => 'text',
        'status' => 'sent',
        'body' => 'Welcome to the gym!',
        'occurred_at' => now(),
    ]);

    expect(fn () => app(AiReplyAssistant::class)->suggestReply($conversation))
        ->toThrow(RuntimeException::class);
});

it('drafts a reply from the conversation history, starting from the first inbound message', function (): void {
    $gym = Gym::factory()->create();
    WhatsappAiSetting::factory()->for($gym)->create(['anthropic_api_key' => 'sk-ant-test']);
    $conversation = WhatsappConversation::factory()->for($gym)->create();

    // Leading outbound message should be trimmed from history.
    $conversation->messages()->create([
        'gym_id' => $gym->id, 'direction' => 'out', 'type' => 'text',
        'status' => 'sent', 'body' => 'Welcome!', 'occurred_at' => now()->subMinutes(10),
    ]);
    $conversation->messages()->create([
        'gym_id' => $gym->id, 'direction' => 'in', 'type' => 'text',
        'status' => 'delivered', 'body' => 'What time do you open?', 'occurred_at' => now()->subMinutes(5),
    ]);

    // Captured by reference via bindFakeAnthropicClient's closure.
    $captured = [];
    app()->bind(AnthropicMessagesClient::class, function () use (&$captured) {
        return new class($captured) implements AnthropicMessagesClient
        {
            public function __construct(private array &$captured) {}

            public function complete(string $apiKey, string $model, string $system, array $messages, int $maxTokens): string
            {
                $this->captured = compact('apiKey', 'model', 'system', 'messages', 'maxTokens');

                return 'We open at 6am!';
            }
        };
    });

    $reply = app(AiReplyAssistant::class)->suggestReply($conversation);

    expect($reply)->toBe('We open at 6am!')
        ->and($captured['apiKey'])->toBe('sk-ant-test')
        ->and($captured['model'])->toBe(AiReplyAssistant::DEFAULT_MODEL)
        ->and($captured['messages'])->toBe([
            ['role' => 'user', 'content' => 'What time do you open?'],
        ]);
});

it('uses the branch-configured model instead of the default', function (): void {
    $gym = Gym::factory()->create();
    WhatsappAiSetting::factory()->for($gym)->create([
        'anthropic_api_key' => 'sk-ant-test',
        'model' => 'claude-haiku-4-5',
    ]);
    $conversation = WhatsappConversation::factory()->for($gym)->create();
    $conversation->messages()->create([
        'gym_id' => $gym->id, 'direction' => 'in', 'type' => 'text',
        'status' => 'delivered', 'body' => 'Hi', 'occurred_at' => now(),
    ]);

    $captured = [];
    app()->bind(AnthropicMessagesClient::class, function () use (&$captured) {
        return new class($captured) implements AnthropicMessagesClient
        {
            public function __construct(private array &$captured) {}

            public function complete(string $apiKey, string $model, string $system, array $messages, int $maxTokens): string
            {
                $this->captured['model'] = $model;

                return 'Hello!';
            }
        };
    });

    app(AiReplyAssistant::class)->suggestReply($conversation);

    expect($captured['model'])->toBe('claude-haiku-4-5');
});

it('includes knowledge base articles in the system prompt only when the feature is enabled', function (): void {
    $gym = Gym::factory()->create();
    WhatsappAiSetting::factory()->for($gym)->create(['anthropic_api_key' => 'sk-ant-test']);
    WhatsappKnowledgeBaseArticle::factory()->for($gym)->create([
        'title' => 'Opening Hours',
        'content' => 'We open at 6am and close at 10pm.',
    ]);
    $conversation = WhatsappConversation::factory()->for($gym)->create();
    $conversation->messages()->create([
        'gym_id' => $gym->id, 'direction' => 'in', 'type' => 'text',
        'status' => 'delivered', 'body' => 'What are your hours?', 'occurred_at' => now(),
    ]);

    $captured = [];
    $bind = function () use (&$captured) {
        app()->bind(AnthropicMessagesClient::class, function () use (&$captured) {
            return new class($captured) implements AnthropicMessagesClient
            {
                public function __construct(private array &$captured) {}

                public function complete(string $apiKey, string $model, string $system, array $messages, int $maxTokens): string
                {
                    $this->captured['system'] = $system;

                    return 'We are open 6am-10pm.';
                }
            };
        });
    };

    Helpers::setTestSettingsOverride(['marketing' => ['knowledge_base' => false]]);
    $bind();
    app(AiReplyAssistant::class)->suggestReply($conversation);
    expect($captured['system'])->not->toContain('Opening Hours');

    Helpers::setTestSettingsOverride(['marketing' => ['knowledge_base' => true]]);
    $bind();
    app(AiReplyAssistant::class)->suggestReply($conversation);
    expect($captured['system'])->toContain('Opening Hours');
});

it('propagates the underlying client failure as a RuntimeException', function (): void {
    $gym = Gym::factory()->create();
    WhatsappAiSetting::factory()->for($gym)->create(['anthropic_api_key' => 'sk-ant-test']);
    $conversation = WhatsappConversation::factory()->for($gym)->create();
    $conversation->messages()->create([
        'gym_id' => $gym->id, 'direction' => 'in', 'type' => 'text',
        'status' => 'delivered', 'body' => 'Hi', 'occurred_at' => now(),
    ]);

    bindFakeAnthropicClient(reply: null);

    expect(fn () => app(AiReplyAssistant::class)->suggestReply($conversation))
        ->toThrow(RuntimeException::class, 'Anthropic API error: simulated failure');
});
