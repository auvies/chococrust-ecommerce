import { adminFetch } from "@/lib/api/admin/client";
import { toQueryString, type ListQueryParams } from "@/lib/api/query";
import type { ApiEnvelope, CodRecord, Paginated, Payment } from "@/types/api";

export async function getPayments(params: ListQueryParams = {}): Promise<Paginated<Payment>> {
  return adminFetch<Paginated<Payment>>(`/v1/payments${toQueryString(params)}`);
}

export async function getPayment(id: number): Promise<Payment> {
  const { data } = await adminFetch<ApiEnvelope<Payment>>(`/v1/payments/${id}`);
  return data;
}

/** Payment-affecting and idempotent (CLAUDE.md §13) - a fresh key per click prevents a double-refund on retry. */
export async function refundPayment(id: number, amount: number, reason: string): Promise<Payment> {
  const { data } = await adminFetch<ApiEnvelope<Payment>>(`/v1/payments/${id}/refund`, {
    method: "POST",
    body: { amount, reason },
    headers: { "Idempotency-Key": crypto.randomUUID() },
  });
  return data;
}

export async function getCodRecords(params: ListQueryParams = {}): Promise<Paginated<CodRecord>> {
  return adminFetch<Paginated<CodRecord>>(`/v1/cod-records${toQueryString(params)}`);
}

// Every COD mutation below is payment-sensitive and the backend route
// requires an Idempotency-Key header (CLAUDE.md §13) - a fresh key per
// click means a retried request replays instead of double-processing.

export async function verifyCodRecord(id: number): Promise<CodRecord> {
  const { data } = await adminFetch<ApiEnvelope<CodRecord>>(`/v1/cod-records/${id}/verify`, {
    method: "PATCH",
    headers: { "Idempotency-Key": crypto.randomUUID() },
  });
  return data;
}

export async function collectCodRecord(id: number, amountCollected: number): Promise<CodRecord> {
  const { data } = await adminFetch<ApiEnvelope<CodRecord>>(`/v1/cod-records/${id}/collect`, {
    method: "PATCH",
    body: { amount_collected: amountCollected },
    headers: { "Idempotency-Key": crypto.randomUUID() },
  });
  return data;
}

export async function failCodRecord(id: number, reason: string): Promise<CodRecord> {
  const { data } = await adminFetch<ApiEnvelope<CodRecord>>(`/v1/cod-records/${id}/fail`, {
    method: "PATCH",
    body: { reason },
    headers: { "Idempotency-Key": crypto.randomUUID() },
  });
  return data;
}

export async function returnCodRecord(id: number, reason?: string): Promise<CodRecord> {
  const { data } = await adminFetch<ApiEnvelope<CodRecord>>(`/v1/cod-records/${id}/return`, {
    method: "PATCH",
    body: { reason },
    headers: { "Idempotency-Key": crypto.randomUUID() },
  });
  return data;
}
