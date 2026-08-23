import { adminFetch } from "@/lib/api/admin/client";
import { toQueryString, type ListQueryParams } from "@/lib/api/query";
import type { AgentPendingAction, ApiEnvelope, Paginated } from "@/types/api";

/**
 * Staff review queue for high-risk agent tool calls (refund, order
 * cancellation, ...) - CLAUDE.md §9/§23's human-approval requirement.
 * Approving still requires the same domain permission the action itself
 * would (e.g. orders.refund) - see AgentPendingActionController's own
 * docblock - so a 403 here can mean "no agent_actions.manage" or "missing
 * the specific domain permission," not just one blanket gate.
 */
export async function getPendingAgentActions(params: ListQueryParams = {}): Promise<Paginated<AgentPendingAction>> {
  return adminFetch<Paginated<AgentPendingAction>>(`/v1/agent-actions${toQueryString(params)}`);
}

export async function approveAgentAction(id: number): Promise<AgentPendingAction> {
  const { data } = await adminFetch<ApiEnvelope<AgentPendingAction>>(`/v1/agent-actions/${id}/approve`, { method: "POST" });
  return data;
}

export async function rejectAgentAction(id: number, reason: string): Promise<AgentPendingAction> {
  const { data } = await adminFetch<ApiEnvelope<AgentPendingAction>>(`/v1/agent-actions/${id}/reject`, {
    method: "POST",
    body: { reason },
  });
  return data;
}
