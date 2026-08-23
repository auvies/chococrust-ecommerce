<?php

namespace App\Services\Ai;

use App\Models\ChatConversation;
use Illuminate\Support\Str;

/**
 * Context/history limits + summarization: once a conversation has more
 * messages than `services.anthropic.max_history_messages` keeps verbatim,
 * everything older is compacted into a short, bounded text blob instead of
 * being resent to the model in full on every request - the concrete
 * mechanism behind "Do NOT send... the entire conversation to the AI on
 * every request."
 *
 * Deliberately deterministic (string truncation/concatenation), not
 * another AI call: summarizing with a model would mean paying for a
 * second model call to save money on a first one, and a long-running
 * conversation would keep re-summarizing on every new message. A rougher,
 * free summary is the right tradeoff here - it only needs to keep the AI
 * oriented on what's already been discussed, not preserve every nuance.
 */
class ChatSummarizationService
{
    private const MAX_SUMMARIZED_LINES = 20;

    private const MAX_SUMMARY_LENGTH = 1500;

    /** Persists (and returns) a fresh summary of every message older than the verbatim window - null if there's nothing old enough to summarize. */
    public function refresh(ChatConversation $conversation): ?string
    {
        $keepVerbatim = (int) config('services.anthropic.max_history_messages', 8);

        // Ordered by id as a tiebreaker too, matching AiChatService::
        // buildHistory()'s own ordering - both need to agree on exactly
        // which messages count as "the last N kept verbatim" vs. "old
        // enough to summarize", or a message could end up in both, or
        // neither.
        $all = $conversation->messages()->orderBy('created_at')->orderBy('id')->get();
        $older = $all->count() > $keepVerbatim ? $all->slice(0, $all->count() - $keepVerbatim) : collect();

        if ($older->isEmpty()) {
            return null;
        }

        $lines = $older->take(self::MAX_SUMMARIZED_LINES)->map(
            fn ($message) => ucfirst($message->sender_type).': '.Str::limit($message->content, 80)
        );

        $summary = Str::limit("Earlier in this conversation:\n".$lines->implode("\n"), self::MAX_SUMMARY_LENGTH);

        $conversation->update(['summary' => $summary]);

        return $summary;
    }
}
