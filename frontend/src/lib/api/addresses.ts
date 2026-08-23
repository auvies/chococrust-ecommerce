// Shared with the admin app's refresh-retry logic despite the "admin" path
// in this import — see AdminAuthProvider's own comment; there's nothing
// admin-specific about a one-shot 401-refresh-and-retry wrapper.
import { adminFetch } from "@/lib/api/admin/client";
import type { ApiEnvelope, Address } from "@/types/api";

export interface AddressPayload {
  label?: string | null;
  type?: "billing" | "shipping" | "both";
  recipient_name: string;
  phone: string;
  line1: string;
  line2?: string | null;
  city: string;
  area?: string | null;
  province?: string | null;
  postal_code?: string | null;
  country?: string;
  is_default?: boolean;
}

export async function getMyAddresses(): Promise<Address[]> {
  const { data } = await adminFetch<ApiEnvelope<Address[]>>("/v1/me/addresses");
  return data;
}

export async function createAddress(payload: AddressPayload): Promise<Address> {
  const { data } = await adminFetch<ApiEnvelope<Address>>("/v1/me/addresses", { method: "POST", body: payload });
  return data;
}

export async function updateAddress(id: number, payload: Partial<AddressPayload>): Promise<Address> {
  const { data } = await adminFetch<ApiEnvelope<Address>>(`/v1/me/addresses/${id}`, { method: "PUT", body: payload });
  return data;
}

export async function deleteAddress(id: number): Promise<void> {
  await adminFetch<void>(`/v1/me/addresses/${id}`, { method: "DELETE" });
}
