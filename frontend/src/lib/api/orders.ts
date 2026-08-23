import { adminFetch } from "@/lib/api/admin/client";
import { toQueryString, type ListQueryParams } from "@/lib/api/query";
import type { ApiEnvelope, Order, Paginated } from "@/types/api";

export interface CheckoutItem {
  product_variant_id: number;
  quantity: number;
  customization_note?: string;
}

export type PaymentMethod = "cod" | "easypaisa" | "jazzcash";

/**
 * Mirrors `config('payments.methods')` (backend/config/payments.php) - the
 * backend is the actual source of truth (a request for anything else is
 * rejected there regardless of what this list says), this only keeps the
 * checkout UI's options in sync with it. Only 'cod' is a live, working
 * payment flow today; the others are accepted and modeled (a Payment row
 * is created at status 'pending') but have no gateway integration yet - no
 * redirect, no OTP, nothing collected here that CLAUDE.md §5/§6 would ever
 * allow a frontend to hold in the first place.
 */
export const PAYMENT_METHODS: { value: PaymentMethod; label: string; live: boolean }[] = [
  { value: "cod", label: "Cash on Delivery", live: true },
  { value: "easypaisa", label: "Easypaisa", live: false },
  { value: "jazzcash", label: "JazzCash", live: false },
];

export interface CheckoutPayload {
  items: CheckoutItem[];
  delivery_type: "local" | "nationwide";
  shipping_address_id: number;
  coupon_code?: string;
  contact_name: string;
  contact_phone: string;
  contact_email?: string;
  notes?: string;
  payment_method: PaymentMethod;
}

export async function getMyOrders(params: ListQueryParams = {}): Promise<Paginated<Order>> {
  return adminFetch<Paginated<Order>>(`/v1/orders${toQueryString(params)}`);
}

export async function getOrder(id: number): Promise<Order> {
  const { data } = await adminFetch<ApiEnvelope<Order>>(`/v1/orders/${id}`);
  return data;
}

/**
 * `idempotencyKey` must be stable across retries of the *same* checkout
 * attempt (generated once when the customer reaches checkout, reused on
 * every submit/retry, replaced only after a successful order or when the
 * cart contents change) — see routes/api/orders.php's `idempotent`
 * middleware, which is what actually prevents a double-click or network
 * retry from placing two orders.
 */
export async function createOrder(payload: CheckoutPayload, idempotencyKey: string): Promise<Order> {
  const { data } = await adminFetch<ApiEnvelope<Order>>("/v1/orders", {
    method: "POST",
    body: payload,
    headers: { "Idempotency-Key": idempotencyKey },
  });
  return data;
}

export async function cancelOrder(id: number, note?: string): Promise<Order> {
  const { data } = await adminFetch<ApiEnvelope<Order>>(`/v1/orders/${id}/cancel`, {
    method: "POST",
    body: note ? { note } : undefined,
  });
  return data;
}

export interface DeliveryEligibility {
  eligible: boolean;
  fee: number;
  estimated_minutes: number | null;
}

/** Preview-only — never places an order. Lets checkout show eligibility/fee/ETA before the customer submits. */
export async function checkDeliveryEligibility(
  items: CheckoutItem[],
  deliveryType: "local" | "nationwide",
  shippingAddressId?: number,
): Promise<DeliveryEligibility> {
  const { data } = await adminFetch<ApiEnvelope<DeliveryEligibility>>("/v1/orders/delivery-eligibility", {
    method: "POST",
    body: { items, delivery_type: deliveryType, shipping_address_id: shippingAddressId },
  });
  return data;
}
