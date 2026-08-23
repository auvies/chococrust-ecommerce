import { adminFetch } from "@/lib/api/admin/client";
import { ApiError } from "@/lib/api/client";
import { toQueryString, type ListQueryParams } from "@/lib/api/query";
import type { ApiEnvelope, ChatConversation, ChatMessage, Paginated } from "@/types/api";

export async function getMyConversations(params: ListQueryParams = {}): Promise<Paginated<ChatConversation>> {
  return adminFetch<Paginated<ChatConversation>>(`/v1/chat/conversations${toQueryString(params)}`);
}

export async function createConversation(): Promise<ChatConversation> {
  const { data } = await adminFetch<ApiEnvelope<ChatConversation>>("/v1/chat/conversations", { method: "POST" });
  return data;
}

export async function getConversation(id: number): Promise<ChatConversation> {
  const { data } = await adminFetch<ApiEnvelope<ChatConversation>>(`/v1/chat/conversations/${id}`);
  return data;
}

export interface SendChatMessageResult {
  message: ChatMessage;
  /** null when the bot didn't reply (staff already handling the conversation, or the message was staff's own). */
  reply: ChatMessage | null;
}

export async function sendChatMessage(conversationId: number, content: string): Promise<SendChatMessageResult> {
  const { data } = await adminFetch<ApiEnvelope<SendChatMessageResult>>(
    `/v1/chat/conversations/${conversationId}/messages`,
    { method: "POST", body: { content } },
  );
  return data;
}

export async function closeConversation(id: number): Promise<ChatConversation> {
  const { data } = await adminFetch<ApiEnvelope<ChatConversation>>(`/v1/chat/conversations/${id}/close`, { method: "POST" });
  return data;
}

/** Chat-specific: the send endpoint is rate-limited (CLAUDE.md §14) - surfaced as a friendly, distinct message rather than a generic error. */
export function isRateLimited(error: unknown): boolean {
  return error instanceof ApiError && error.status === 429;
}
