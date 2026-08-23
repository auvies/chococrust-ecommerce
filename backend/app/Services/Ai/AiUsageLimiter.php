<?php

namespace App\Services\Ai;

use App\Models\AiUsageLog;
use App\Models\ChatConversation;
use App\Models\User;

/**
 * CLAUDE.md §19/§23: configurable usage limits, centralized in one place
 * rather than each AI-touching code path inventing its own budget check.
 * Every limit here reads directly from `ai_usage_logs` (the same durable
 * audit trail CLAUDE.md §9 already required), not an in-memory counter, so
 * it holds across requests/processes/deploys with no new infrastructure -
 * the same reasoning the original per-conversation check (moved here from
 * AiChatService) already established.
 *
 * A limit of 0 (the default for every global cap) means "disabled" - this
 * project runs with no live Anthropic key in any environment yet (ADR
 * 0012), so a hard global cap would just be dead weight until a real
 * account exists; the per-conversation and per-agent hourly caps stay
 * enabled by default because they're the ones a single bad actor (a
 * scripted chat client, a mis-provisioned agent identity) could otherwise
 * spam even at zero real cost.
 */
class AiUsageLimiter
{
    /**
     * A cost circuit breaker distinct from the HTTP-level `chat` rate limit
     * (which caps request volume) - this caps specifically how many *paid*
     * model calls one conversation can trigger per hour.
     */
    public function conversationHourlyLimitExceeded(ChatConversation $conversation): bool
    {
        $limit = (int) config('services.anthropic.max_ai_calls_per_conversation_per_hour', 15);

        $recentAiCalls = AiUsageLog::where('chat_conversation_id', $conversation->id)
            ->where('provider', 'anthropic')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return $recentAiCalls >= $limit;
    }

    /**
     * The agent tool-call surface (Phase 13) had no volume cap at all
     * before this - an allow-listed, low-risk tool (e.g.
     * check_product_availability) could otherwise be called without limit
     * by a single agent identity. Scoped per agent user, not globally, so
     * one over-eager agent doesn't starve every other agent's budget.
     */
    public function agentHourlyLimitExceeded(User $agent): bool
    {
        $limit = (int) config('services.anthropic.max_agent_tool_calls_per_hour', 30);

        $recentToolCalls = AiUsageLog::where('user_id', $agent->id)
            ->where('purpose', 'agent_tool_call')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return $recentToolCalls >= $limit;
    }

    /** Global spend cap across every provider/purpose for the current calendar day. Disabled (0) by default. */
    public function dailyCostLimitExceeded(): bool
    {
        $limit = (float) config('services.anthropic.daily_cost_limit_usd', 0);

        if ($limit <= 0) {
            return false;
        }

        $spentToday = (float) AiUsageLog::where('created_at', '>=', now()->startOfDay())->sum('cost_usd');

        return $spentToday >= $limit;
    }

    /** Global request-volume cap across every provider/purpose for the current calendar day. Disabled (0) by default. */
    public function dailyRequestLimitExceeded(): bool
    {
        $limit = (int) config('services.anthropic.daily_request_limit', 0);

        if ($limit <= 0) {
            return false;
        }

        $requestsToday = AiUsageLog::where('created_at', '>=', now()->startOfDay())->count();

        return $requestsToday >= $limit;
    }

    /** Either global cap tripping is enough to refuse a new call, regardless of purpose. */
    public function globalLimitExceeded(): bool
    {
        return $this->dailyCostLimitExceeded() || $this->dailyRequestLimitExceeded();
    }
}
