<?php

use App\Contracts\AiChatClient;
use App\Contracts\AiChatClientFactory;
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
 * Binds an AiChatClientFactory whose make() always returns one fake
 * AiChatClient (regardless of which provider is requested) that either
 * returns a canned reply or (when $reply is null) throws, simulating
 * an upstream API failure. Lets these tests exercise the real
 * guard/history-building/provider-selection logic in AiReplyAssistant
 * without touching any real HTTP client.
 */
function bindFakeAiChatClient(?string $reply): void
{
    app()->bind(AiChatClientFactory::class, function () use ($reply) {
        return new class($reply) implements AiChatClientFactory
        {
            public function __construct(private ?string $reply) {}

            public function make(string $provider, ?string $baseUrlOverride = null): AiChatClient
            {
                return new class($this->reply) implements AiChatClient
                {
                    public function __construct(private ?string $reply) {}

                    public function complete(string $apiKey, string $model, string $system, array $messages, int $maxTokens): string
                    {
                        if ($this->reply === null) {
                            throw new RuntimeException('AI provider API error: simulated failure');
                        }

                        return $this->reply;
                    }
                };
            }
        };
    });
}

/**
 * Binds a factory that captures every argument passed to make() and
 * complete() by reference, for tests that need to assert on what was
 * actually sent (provider, base URL, api key, model, messages, system).
 *
 * @param  array<string, mixed>  $captured
 */
function bindCapturingAiChatClient(array &$captured, string $reply = 'A reply.'): void
{
    app()->bind(AiChatClientFactory::class, function () use (&$captured, $reply) {
        return new class($captured, $reply) implements AiChatClientFactory
        {
            public function __construct(private array &$captured, private string $reply) {}

            public function make(string $provider, ?string $baseUrlOverride = null): AiChatClient
            {
                $this->captured['provider'] = $provider;
                $this->captured['baseUrlOverride'] = $baseUrlOverride;

                return new class($this->captured, $this->reply) implements AiChatClient
                {
                    public function __construct(private array &$captured, private string $reply) {}

                    public function complete(string $apiKey, string $model, string $system, array $messages, int $maxTokens): string
                    {
                        $this->captured['apiKey'] = $apiKey;
                        $this->captured['model'] = $model;
                        $this->captured['system'] = $system;
                        $this->captured['messages'] = $messages;
                        $this->captured['maxTokens'] = $maxTokens;

                        return $this->reply;
                    }
                };
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
    WhatsappAiSetting::factory()->for($gym)->create(['api_key' => 'sk-ant-test']);
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

    $captured = [];
    bindCapturingAiChatClient($captured, 'We open at 6am!');

    $reply = app(AiReplyAssistant::class)->suggestReply($conversation);

    expect($reply)->toBe('We open at 6am!')
        ->and($captured['provider'])->toBe('anthropic')
        ->and($captured['apiKey'])->toBe('sk-ant-test')
        ->and($captured['model'])->toBe(AiReplyAssistant::DEFAULT_MODEL)
        ->and($captured['messages'])->toBe([
            ['role' => 'user', 'content' => 'What time do you open?'],
        ]);
});

it('uses the branch-configured model instead of the default', function (): void {
    $gym = Gym::factory()->create();
    WhatsappAiSetting::factory()->for($gym)->create([
        'api_key' => 'sk-ant-test',
        'model' => 'claude-haiku-4-5',
    ]);
    $conversation = WhatsappConversation::factory()->for($gym)->create();
    $conversation->messages()->create([
        'gym_id' => $gym->id, 'direction' => 'in', 'type' => 'text',
        'status' => 'delivered', 'body' => 'Hi', 'occurred_at' => now(),
    ]);

    $captured = [];
    bindCapturingAiChatClient($captured);

    app(AiReplyAssistant::class)->suggestReply($conversation);

    expect($captured['model'])->toBe('claude-haiku-4-5');
});

it('uses the branch-configured provider and base URL instead of the default', function (): void {
    $gym = Gym::factory()->create();
    WhatsappAiSetting::factory()->for($gym)->create([
        'provider' => 'kimi',
        'api_key' => 'sk-kimi-test',
        'model' => 'kimi-k2.6',
        'base_url' => 'https://api.moonshot.ai/v1',
    ]);
    $conversation = WhatsappConversation::factory()->for($gym)->create();
    $conversation->messages()->create([
        'gym_id' => $gym->id, 'direction' => 'in', 'type' => 'text',
        'status' => 'delivered', 'body' => 'Hi', 'occurred_at' => now(),
    ]);

    $captured = [];
    bindCapturingAiChatClient($captured);

    app(AiReplyAssistant::class)->suggestReply($conversation);

    expect($captured['provider'])->toBe('kimi')
        ->and($captured['baseUrlOverride'])->toBe('https://api.moonshot.ai/v1')
        ->and($captured['apiKey'])->toBe('sk-kimi-test')
        ->and($captured['model'])->toBe('kimi-k2.6');
});

it('includes knowledge base articles in the system prompt only when the feature is enabled', function (): void {
    $gym = Gym::factory()->create();
    WhatsappAiSetting::factory()->for($gym)->create(['api_key' => 'sk-ant-test']);
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

    Helpers::setTestSettingsOverride(['marketing' => ['knowledge_base' => false]]);
    bindCapturingAiChatClient($captured);
    app(AiReplyAssistant::class)->suggestReply($conversation);
    expect($captured['system'])->not->toContain('Opening Hours');

    Helpers::setTestSettingsOverride(['marketing' => ['knowledge_base' => true]]);
    bindCapturingAiChatClient($captured);
    app(AiReplyAssistant::class)->suggestReply($conversation);
    expect($captured['system'])->toContain('Opening Hours');
});

it('propagates the underlying client failure as a RuntimeException', function (): void {
    $gym = Gym::factory()->create();
    WhatsappAiSetting::factory()->for($gym)->create(['api_key' => 'sk-ant-test']);
    $conversation = WhatsappConversation::factory()->for($gym)->create();
    $conversation->messages()->create([
        'gym_id' => $gym->id, 'direction' => 'in', 'type' => 'text',
        'status' => 'delivered', 'body' => 'Hi', 'occurred_at' => now(),
    ]);

    bindFakeAiChatClient(reply: null);

    expect(fn () => app(AiReplyAssistant::class)->suggestReply($conversation))
        ->toThrow(RuntimeException::class, 'AI provider API error: simulated failure');
});
