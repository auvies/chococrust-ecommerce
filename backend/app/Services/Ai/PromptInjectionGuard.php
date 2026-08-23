<?php

namespace App\Services\Ai;

use App\Models\ChatConversation;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * CLAUDE.md §10: user-supplied chat text is always data, never instructions
 * - the actual guarantee against prompt injection is structural
 * (AnthropicChatProvider sends it as a `user`-role message, never
 * concatenated into the system prompt string), but this class adds two
 * layers of defense in depth on top of that structural guarantee:
 *
 * 1. `scanInbound()` - a message that looks like a jailbreak/override
 *    attempt is logged as an anomaly (CLAUDE.md §10: "logged... and the
 *    anomaly is surfaced, not silently obeyed") and never even reaches the
 *    paid model - AiChatService treats a flagged message like any other
 *    deterministic case, replying with a fixed, safe message instead. This
 *    is both a security control and a cost control: no reason to spend a
 *    model call on a message that's already been identified as an attack.
 * 2. `sanitizeOutbound()` - even though the system prompt instructs the
 *    model to never reveal secrets/keys/internal tools (see
 *    AiChatService::systemPrompt()), model behavior is probabilistic, not
 *    guaranteed - this is a hard, code-enforced backstop that strips
 *    anything shaped like a real credential from the response before it
 *    ever reaches the customer, regardless of what the model said.
 * 3. `sanitizeRetrievedContext()` - a security fix (SECURITY_AUDIT.md):
 *    `retrieveRelevantContext()`'s product name/description text is
 *    concatenated directly into the *system prompt* (not sent as a
 *    `user`-role message), so it was previously trusted without ever being
 *    scanned - a compromised or malicious staff account (content_manager/
 *    manager/super_admin all hold `products.manage`) could plant
 *    instruction-shaped text in a product description that a customer's
 *    question would then retrieve and have treated as system-level
 *    context. CLAUDE.md §10: "If the chatbot or an agent retrieves
 *    external content..., that content is explicitly framed as untrusted
 *    reference data in the prompt, not as commands" - applied here to
 *    admin-authored catalog content the same way it already applied to
 *    customer messages, on the assumption that an account being staff
 *    doesn't make its data trustworthy against a prompt-injection payload.
 */
class PromptInjectionGuard
{
    private const INBOUND_PATTERNS = [
        '/ignore\s+(all|any|previous|prior|the\s+above)\s+instructions?/i',
        '/disregard\s+(all|your|any|previous|prior)\s+(instructions?|rules?|prompt)/i',
        '/you\s+are\s+now\s+(a|an|in)\b/i',
        '/(reveal|show|print|output|repeat)\s+(your|the)\s+(system\s+prompt|instructions?|api\s*key|secret|credentials?)/i',
        '/what\s+(is|are)\s+your\s+(system\s+prompt|instructions?|rules?)/i',
        '/pretend\s+(you\s+are|to\s+be)\b/i',
        '/act\s+as\s+(if\s+you|a|an)\b/i',
        '/\bDAN\b/',
        '/developer\s+mode/i',
        '/jailbreak/i',
        '/\bsudo\b/i',
    ];

    /** Backstop patterns for genuine secret shapes - never expected to legitimately appear in a chat reply. */
    private const OUTBOUND_SECRET_PATTERNS = [
        '/sk-ant-[A-Za-z0-9\-_]{10,}/' => '[redacted]',
        '/sk-[A-Za-z0-9]{20,}/' => '[redacted]',
        '/\bANTHROPIC_[A-Z_]*\s*[:=]\s*\S+/' => '[redacted]',
        '/\b[A-Z_]*(API|SECRET)_KEY\s*[:=]\s*\S+/i' => '[redacted]',
        '/\bpassword\s*[:=]\s*\S+/i' => '[redacted]',
    ];

    public function scanInbound(?ChatConversation $conversation, string $message): bool
    {
        foreach (self::INBOUND_PATTERNS as $pattern) {
            if (preg_match($pattern, $message) === 1) {
                $excerpt = Str::limit($message, 200);

                Log::warning('chat.prompt_injection_suspected', [
                    'conversation_id' => $conversation?->id,
                    'excerpt' => $excerpt,
                ]);

                // CLAUDE.md §10: a flagged anomaly is logged and surfaced,
                // not silently obeyed - the application log above is
                // ephemeral/unstructured, so this is the durable, queryable
                // record a super_admin reviewing security events actually
                // finds (CLAUDE.md §15's "security events" audit category).
                AuditLogger::log(
                    'security.prompt_injection_detected',
                    $conversation,
                    after: ['excerpt' => $excerpt],
                );

                return true;
            }
        }

        return false;
    }

    public function sanitizeOutbound(string $text): string
    {
        foreach (self::OUTBOUND_SECRET_PATTERNS as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        return $text;
    }

    /**
     * Neutralizes instruction-shaped text in content retrieved from the
     * database (product name/description) before it's concatenated into
     * the system prompt - the same INBOUND_PATTERNS used to flag a whole
     * customer message, applied instead to strip just the offending
     * fragment so a legitimate product description isn't discarded
     * wholesale over one suspicious substring.
     */
    public function sanitizeRetrievedContext(string $text): string
    {
        foreach (self::INBOUND_PATTERNS as $pattern) {
            $text = preg_replace($pattern, '[removed]', $text) ?? $text;
        }

        return $text;
    }
}
