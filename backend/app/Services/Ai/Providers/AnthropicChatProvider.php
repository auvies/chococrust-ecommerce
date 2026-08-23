<?php

namespace App\Services\Ai\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The only place in the codebase that makes an outbound call to the AI
 * provider. `complete()` always sends the system prompt and every prior
 * turn through the API's own structured `system`/`messages` fields - never
 * string-concatenated together - which is what actually makes prompt
 * injection structurally hard, not just discouraged by instruction
 * (CLAUDE.md §10).
 *
 * Response tokens are hard-capped by `max_tokens` on every call
 * (CLAUDE.md's "Response token limits" requirement) - the API itself stops
 * generating once that many tokens are produced, so cost per call has a
 * real ceiling regardless of what the model would otherwise have said.
 *
 * No API key configured (true in every environment today - see
 * config/services.php's own docblock) throws immediately, before any
 * network call is attempted, so this is fully exercisable/testable without
 * outbound network access.
 */
class AnthropicChatProvider
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function complete(string $systemPrompt, array $messages): AiChatResult
    {
        $apiKey = config('services.anthropic.api_key');

        if (! $apiKey) {
            throw new AiProviderUnavailableException('No Anthropic API key configured.');
        }

        $model = config('services.anthropic.model');
        $maxTokens = (int) config('services.anthropic.max_tokens');

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])
                ->timeout((int) config('services.anthropic.timeout_seconds', 15))
                ->post(rtrim((string) config('services.anthropic.base_url'), '/').'/v1/messages', [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'system' => $systemPrompt,
                    'messages' => $messages,
                ]);
        } catch (Throwable $e) {
            Log::warning('chat.ai_provider_call_failed', ['error' => $e->getMessage()]);

            throw new AiProviderUnavailableException('Anthropic API call failed.', previous: $e);
        }

        if ($response->failed()) {
            Log::warning('chat.ai_provider_call_failed', ['status' => $response->status(), 'body' => $response->body()]);

            throw new AiProviderUnavailableException("Anthropic API returned {$response->status()}.");
        }

        $body = $response->json();
        $text = collect($body['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        if ($text === '') {
            throw new AiProviderUnavailableException('Anthropic API returned no text content.');
        }

        return new AiChatResult(
            content: $text,
            inputTokens: (int) ($body['usage']['input_tokens'] ?? 0),
            outputTokens: (int) ($body['usage']['output_tokens'] ?? 0),
            model: $model,
        );
    }
}
