import { adminFetch } from "@/lib/api/admin/client";
import { toQueryString, type ListQueryParams } from "@/lib/api/query";
import type { ApiEnvelope, DeliveryRule, Paginated } from "@/types/api";

export interface DeliveryRulePayload {
  scope: "global" | "category" | "product";
  category_id?: number | null;
  product_id?: number | null;
  delivery_type: "local" | "nationwide" | "both";
  is_deliverable?: boolean;
  min_order_amount?: number | null;
  flat_fee?: number | null;
  local_areas?: string[] | null;
  estimated_minutes?: number | null;
  priority?: number;
  is_active?: boolean;
}

export async function getDeliveryRules(params: ListQueryParams = {}): Promise<Paginated<DeliveryRule>> {
  return adminFetch<Paginated<DeliveryRule>>(`/v1/delivery-rules${toQueryString(params)}`);
}

export async function createDeliveryRule(payload: DeliveryRulePayload): Promise<DeliveryRule> {
  const { data } = await adminFetch<ApiEnvelope<DeliveryRule>>("/v1/delivery-rules", { method: "POST", body: payload });
  return data;
}

export async function updateDeliveryRule(id: number, payload: Partial<DeliveryRulePayload>): Promise<DeliveryRule> {
  const { data } = await adminFetch<ApiEnvelope<DeliveryRule>>(`/v1/delivery-rules/${id}`, {
    method: "PUT",
    body: payload,
  });
  return data;
}

export async function deleteDeliveryRule(id: number): Promise<void> {
  await adminFetch<void>(`/v1/delivery-rules/${id}`, { method: "DELETE" });
}
