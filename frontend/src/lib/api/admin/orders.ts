import { adminFetch } from "@/lib/api/admin/client";
import { toQueryString, type ListQueryParams } from "@/lib/api/query";
import type { ApiEnvelope, Order, OrderStatus, Paginated } from "@/types/api";

/** Mirrors OrderService::TRANSITIONS (backend/app/Services/Orders/OrderService.php) so the UI only offers valid next statuses. */
export const ORDER_TRANSITIONS: Record<OrderStatus, OrderStatus[]> = {
  pending: ["confirmed", "cancelled"],
  confirmed: ["processing", "cancelled"],
  processing: ["ready", "cancelled"],
  ready: ["dispatched", "cancelled"],
  dispatched: ["delivered"],
  delivered: ["completed", "refunded"],
  completed: ["refunded"],
  cancelled: [],
  refunded: [],
};

export async function getOrders(params: ListQueryParams = {}): Promise<Paginated<Order>> {
  return adminFetch<Paginated<Order>>(`/v1/orders${toQueryString(params)}`);
}

export async function getOrder(id: number): Promise<Order> {
  const { data } = await adminFetch<ApiEnvelope<Order>>(`/v1/orders/${id}`);
  return data;
}

export async function updateOrderStatus(id: number, status: OrderStatus, note?: string): Promise<Order> {
  const { data } = await adminFetch<ApiEnvelope<Order>>(`/v1/orders/${id}/status`, {
    method: "PATCH",
    body: { status, note },
  });
  return data;
}

export async function cancelOrder(id: number, note?: string): Promise<Order> {
  const { data } = await adminFetch<ApiEnvelope<Order>>(`/v1/orders/${id}/cancel`, {
    method: "POST",
    body: { note },
  });
  return data;
}
