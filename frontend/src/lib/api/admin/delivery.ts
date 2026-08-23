import { adminFetch } from "@/lib/api/admin/client";
import { toQueryString, type ListQueryParams } from "@/lib/api/query";
import type { ApiEnvelope, Delivery, Paginated } from "@/types/api";

export async function getDeliveries(params: ListQueryParams = {}): Promise<Paginated<Delivery>> {
  return adminFetch<Paginated<Delivery>>(`/v1/deliveries${toQueryString(params)}`);
}

export async function updateDeliveryStatus(
  id: number,
  status: string,
  location?: string,
  note?: string,
  failureReason?: string,
): Promise<Delivery> {
  const { data } = await adminFetch<ApiEnvelope<Delivery>>(`/v1/deliveries/${id}/status`, {
    method: "PATCH",
    body: { status, location, note, failure_reason: failureReason },
  });
  return data;
}
