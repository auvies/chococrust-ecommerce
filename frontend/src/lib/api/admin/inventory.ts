import { adminFetch } from "@/lib/api/admin/client";
import { toQueryString, type ListQueryParams } from "@/lib/api/query";
import type { ApiEnvelope, Inventory, InventoryMovement, Paginated } from "@/types/api";

export async function getInventory(params: ListQueryParams = {}): Promise<Paginated<Inventory>> {
  return adminFetch<Paginated<Inventory>>(`/v1/inventory${toQueryString(params)}`);
}

export async function createInventoryRecord(payload: {
  product_variant_id: number;
  location?: string;
  quantity_on_hand?: number;
  reorder_level?: number | null;
}): Promise<Inventory> {
  const { data } = await adminFetch<ApiEnvelope<Inventory>>("/v1/inventory", { method: "POST", body: payload });
  return data;
}

export async function adjustInventory(id: number, delta: number, reason: string): Promise<Inventory> {
  const { data } = await adminFetch<ApiEnvelope<Inventory>>(`/v1/inventory/${id}/adjust`, {
    method: "PATCH",
    body: { delta, reason },
  });
  return data;
}

/** "Inventory history" - every reservation/commit/release/sale/adjustment for this row's variant, newest first. */
export async function getInventoryHistory(id: number, params: ListQueryParams = {}): Promise<Paginated<InventoryMovement>> {
  return adminFetch<Paginated<InventoryMovement>>(`/v1/inventory/${id}/history${toQueryString(params)}`);
}
