<?php

namespace App\Services\Agents\Contracts;

use App\Models\User;

/**
 * Agent -> Approved Tool -> Validation -> Authorization -> Business Logic
 * -> Database (the business's own pipeline). Every concrete tool is one
 * class satisfying this contract; `AgentToolGateway` is the only thing
 * that ever calls `handle()`, and only after resolving the tool by name
 * from `AgentToolRegistry` (Approved Tool), validating `$input` against
 * `rules()` (Validation), and checking the caller's agent type against
 * `allowedAgentTypes()` (Authorization).
 *
 * `handle()` must only ever call an existing Service class (OrderService,
 * PaymentService, InventoryService, ...) or a narrowly-scoped read query
 * already vetted for safety - never raw SQL, never a query built from
 * unescaped `$input`, and never direct Eloquent mass-assignment of
 * arbitrary agent-supplied fields. This is what "agents must not directly
 * access the database" actually means in code: the database is reached
 * exclusively through the same business-logic layer human-triggered
 * requests already go through.
 */
interface AgentToolInterface
{
    /** The name a tool call's `tool` field must match exactly. */
    public function name(): string;

    /**
     * Which agent types may call this tool. An empty array means
     * unrestricted (available to any authenticated agent identity) - used
     * for the two tools that predate agent-type scoping (Phase 12) and
     * still need to work for an agent with no `agent_type` set.
     *
     * @return array<int, string> AgentType::* values
     */
    public function allowedAgentTypes(): array;

    /**
     * High-risk tools (refund, payment verification, large discount, price
     * changes, bulk deletion, order cancellation, dispatch cancellation -
     * the business's own list) never execute from a tool call directly;
     * AgentToolGateway routes them into AgentApprovalService's pending
     * queue instead, and only a human approval actually invokes handle().
     */
    public function isHighRisk(): bool;

    /**
     * The permission a human reviewer must hold to approve this specific
     * tool's pending actions - the same permission that would let them
     * perform the equivalent action directly (approving a refund request
     * needs `orders.refund`, the same as issuing one by hand), rather than
     * one blanket "can approve anything" permission. Only meaningful when
     * `isHighRisk()` is true; low-risk tools are never queued for approval.
     */
    public function requiredApprovalPermission(): string;

    /** Laravel validation rules - CLAUDE.md's "strict schemas for tools," enforced before handle() ever runs. */
    public function rules(): array;

    /**
     * The business logic itself. `$actor` is whoever is authorizing this
     * specific execution right now - the calling agent for a low-risk
     * tool, or the human reviewer for a high-risk tool once approved (see
     * AgentApprovalService::approve()) - so audit trails always name a
     * real accountable party, not just "the AI."
     *
     * @return array<string, mixed> a small, bounded result - never a raw model dump (CLAUDE.md §19's token-cost principle applies to tool output too).
     */
    public function handle(array $input, User $actor): array;
}
