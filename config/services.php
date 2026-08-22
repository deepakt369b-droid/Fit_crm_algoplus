<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        // Meta's Graph API version increments regularly; check
        // developers.facebook.com/docs/graph-api/changelog before
        // assuming this is still current.
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'base_url' => env('WHATSAPP_API_BASE_URL', 'https://graph.facebook.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Reply Assistant Providers
    |--------------------------------------------------------------------------
    |
    | Backs the WhatsApp AI reply assistant (see App\Services\WhatsApp\
    | AiReplyAssistant / AiChatClientFactory). "openai", "kimi", and
    | "glm" all go through the same OpenAI-compatible /chat/completions
    | client — only base_url differs. default_model is only used when a
    | branch leaves its model field blank in Settings (Marketing tab);
    | the field always accepts free text, so a stale default here is
    | never a hard blocker, just a fallback. Model names move fast —
    | verify against each provider's own docs before relying on a
    | default rather than assuming these are still current:
    |   - Anthropic: docs.claude.com
    |   - OpenAI:    platform.openai.com/docs/models
    |   - Kimi:      platform.moonshot.ai (international) — the
    |                moonshot-v1-* series sunsets 2026-08-31, use a
    |                kimi-k2.x/k3.x model instead
    |   - GLM:       open.bigmodel.cn (China) or api.z.ai (international)
    |                — base_url below is the China endpoint; override
    |                per-branch via the AI settings "API Base URL" field
    |                to use the international one instead
    |
    */

    'ai_providers' => [
        'anthropic' => [
            'label' => 'Anthropic (Claude)',
            'base_url' => 'https://api.anthropic.com/v1',
            'default_model' => 'claude-opus-5',
        ],
        'openai' => [
            'label' => 'OpenAI',
            'base_url' => 'https://api.openai.com/v1',
            'default_model' => 'gpt-4o',
        ],
        'kimi' => [
            'label' => 'Kimi (Moonshot AI)',
            'base_url' => 'https://api.moonshot.ai/v1',
            'default_model' => 'kimi-k2.6',
        ],
        'glm' => [
            'label' => 'GLM (Zhipu AI)',
            'base_url' => 'https://open.bigmodel.cn/api/paas/v4',
            'default_model' => 'glm-4.6',
        ],
    ],

];
