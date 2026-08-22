<?php

use App\Contracts\SettingsRepository;
use App\Filament\Pages\Settings;
use App\Models\WhatsappAiSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('persists settings via the settings repository when saving', function (): void {
    $repository = new class implements SettingsRepository
    {
        /**
         * @var array<string, mixed>
         */
        public array $lastPut = [];

        public function get(): array
        {
            return [
                'general' => [],
                'invoice' => [],
                'member' => [],
                'charges' => [],
                'expenses' => [],
                'subscriptions' => [],
                'payments' => [],
                'notifications' => [
                    'email' => [],
                ],
            ];
        }

        public function put(array $settings): void
        {
            $this->lastPut = $settings;
        }
    };

    app()->instance(SettingsRepository::class, $repository);

    Livewire::test(Settings::class)
        ->set('data', [
            'general' => [
                'financial_year_start' => '2026-04-15',
                'financial_year_end' => null,
                'gym_logo' => ['images/logo.png'],
            ],
            'invoice' => [],
            'member' => [],
            'charges' => [],
            'expenses' => [],
            'subscriptions' => [],
            'payments' => [],
            'notifications' => [
                'email' => [],
            ],
        ])
        ->call('save');

    expect($repository->lastPut)
        ->toHaveKey('general')
        ->and($repository->lastPut['general']['financial_year_start'])->toBe('2026-04-01')
        ->and($repository->lastPut['general']['financial_year_end'])->toBe('2027-03-31')
        ->and($repository->lastPut['general']['gym_logo'])->toBe('images/logo.png');
});

/**
 * @return array<string, mixed>
 */
function baseSettingsFixture(): array
{
    return [
        'general' => [],
        'invoice' => [],
        'member' => [],
        'charges' => [],
        'expenses' => [],
        'subscriptions' => [],
        'payments' => [],
        'notifications' => ['email' => []],
        'marketing' => [],
    ];
}

it('saves the AI assistant API key to WhatsappAiSetting, not the JSON settings blob', function (): void {
    Livewire::test(Settings::class)
        ->set('data', array_merge(baseSettingsFixture(), [
            'marketing' => [
                'ai_api_key' => 'sk-ant-brand-new-key',
                'ai_model' => 'claude-sonnet-5',
                'ai_system_prompt' => 'Always mention our summer promo.',
            ],
        ]))
        ->call('save');

    $setting = WhatsappAiSetting::query()->sole();

    expect($setting->anthropic_api_key)->toBe('sk-ant-brand-new-key')
        ->and($setting->model)->toBe('claude-sonnet-5')
        ->and($setting->system_prompt)->toBe('Always mention our summer promo.');

    $stored = app(SettingsRepository::class)->get();
    expect($stored['marketing'] ?? [])->not->toHaveKey('ai_api_key');
});

it('keeps the current AI API key when the field is submitted blank', function (): void {
    WhatsappAiSetting::factory()->create([
        'anthropic_api_key' => 'sk-ant-existing-key',
        'model' => 'claude-opus-5',
    ]);

    Livewire::test(Settings::class)
        ->set('data', array_merge(baseSettingsFixture(), [
            'marketing' => [
                'ai_api_key' => null,
                'ai_model' => 'claude-haiku-4-5',
            ],
        ]))
        ->call('save');

    $setting = WhatsappAiSetting::query()->sole();

    expect($setting->anthropic_api_key)->toBe('sk-ant-existing-key')
        ->and($setting->model)->toBe('claude-haiku-4-5');
});
