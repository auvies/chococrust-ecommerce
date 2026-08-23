<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business API (preparation only - CLAUDE.md §6)
    |--------------------------------------------------------------------------
    |
    | No WhatsApp Business API account exists yet, so `enabled` defaults to
    | false and App\Notifications\Channels\WhatsAppChannel never makes an
    | outbound call regardless of what's set here - see that class's own
    | docblock. When a real account exists, its credentials go here (env-
    | driven, never hardcoded, never exposed to the frontend) and only
    | WhatsAppChannel::send() needs to change to actually call the provider.
    |
    */

    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'api_key' => env('WHATSAPP_API_KEY'),
        'from_number' => env('WHATSAPP_FROM_NUMBER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Facebook / Instagram (preparation only - CLAUDE.md §6)
    |--------------------------------------------------------------------------
    |
    | No Meta developer app or Instagram Business account is connected yet.
    | These values are read only by SocialAccountController::status() (which
    | reports connected: true/false per platform, never the secret values
    | themselves) and are never referenced anywhere a frontend response
    | could echo them - see that controller's own docblock for the full
    | "prepared, not built" reasoning, the same posture as WhatsApp above
    | and Easypaisa/JazzCash (ADR 0009).
    |
    */

    'facebook' => [
        'enabled' => env('FACEBOOK_ENABLED', false),
        'app_id' => env('FACEBOOK_APP_ID'),
        'app_secret' => env('FACEBOOK_APP_SECRET'),
        'page_id' => env('FACEBOOK_PAGE_ID'),
        'page_access_token' => env('FACEBOOK_PAGE_ACCESS_TOKEN'),
    ],

    'instagram' => [
        'enabled' => env('INSTAGRAM_ENABLED', false),
        'business_account_id' => env('INSTAGRAM_BUSINESS_ACCOUNT_ID'),
        'access_token' => env('INSTAGRAM_ACCESS_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Chatbot (Anthropic)
    |--------------------------------------------------------------------------
    |
    | Unlike WhatsApp/Facebook/Instagram above, this one is meant to work
    | today, not just be prepared - AiChatService's deterministic path
    | (prices/stock/delivery/hours/payment methods/order status/contact -
    | CLAUDE.md's own list) needs no API key at all. Only the "AI is
    | genuinely necessary" branch calls out here, and gracefully falls back
    | to a safe canned message if `api_key` is empty rather than attempting
    | a call that would fail - see AnthropicChatProvider.
    |
    | `model` defaults to the smallest/cheapest Claude model (CLAUDE.md
    | §19: "use the smallest/cheapest model that reliably meets the task's
    | quality bar"). `max_tokens` hard-caps the response length (and
    | therefore cost) of every single call. `max_history_messages` is how
    | many recent turns are sent verbatim - anything older is compacted by
    | ChatSummarizationService instead of resent in full on every request.
    | `max_ai_calls_per_conversation_per_hour` is a cost circuit breaker
    | independent of the general per-route rate limit (see the `chat`
    | RateLimiter in AppServiceProvider) - it caps specifically the
    | *paid* calls a single conversation can trigger, not just requests.
    |
    | `max_agent_tool_calls_per_hour`, `daily_cost_limit_usd`, and
    | `daily_request_limit` are the same idea generalized to the agent
    | tool-call surface (Phase 13) and to a global cap - see
    | App\Services\Ai\AiUsageLimiter, the single place all of these are
    | actually enforced. The two daily caps default to 0 ("disabled"),
    | consistent with no live Anthropic key existing in any environment
    | yet; set them once real spend needs a hard ceiling.
    |
    */

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5'),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 400),
        'max_history_messages' => (int) env('ANTHROPIC_MAX_HISTORY_MESSAGES', 8),
        'max_ai_calls_per_conversation_per_hour' => (int) env('ANTHROPIC_MAX_CALLS_PER_HOUR', 15),
        'max_agent_tool_calls_per_hour' => (int) env('ANTHROPIC_MAX_AGENT_TOOL_CALLS_PER_HOUR', 30),
        'daily_cost_limit_usd' => (float) env('ANTHROPIC_DAILY_COST_LIMIT_USD', 0),
        'daily_request_limit' => (int) env('ANTHROPIC_DAILY_REQUEST_LIMIT', 0),
        'timeout_seconds' => (int) env('ANTHROPIC_TIMEOUT_SECONDS', 15),
        // Input cost, output cost - USD per million tokens (Claude Haiku
        // 4.5 list pricing at the time this was written). Used only to
        // compute the `cost_usd` estimate stored on ai_usage_logs for cost
        // monitoring - never sent anywhere, never billed from directly.
        'input_cost_per_million' => (float) env('ANTHROPIC_INPUT_COST_PER_MILLION', 1.0),
        'output_cost_per_million' => (float) env('ANTHROPIC_OUTPUT_COST_PER_MILLION', 5.0),
    ],

];
