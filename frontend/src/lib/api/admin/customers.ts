import { adminFetch } from "@/lib/api/admin/client";
import { toQueryString, type ListQueryParams } from "@/lib/api/query";
import type { ApiEnvelope, Customer, CustomerNote, CustomerTag, Order, Paginated } from "@/types/api";

export async function getCustomers(params: ListQueryParams = {}): Promise<Paginated<Customer>> {
  return adminFetch<Paginated<Customer>>(`/v1/customers${toQueryString(params)}`);
}

export async function getCustomer(id: number): Promise<Customer> {
  const { data } = await adminFetch<ApiEnvelope<Customer>>(`/v1/customers/${id}`);
  return data;
}

export async function updateCustomerNotes(id: number, notes: string): Promise<Customer> {
  const { data } = await adminFetch<ApiEnvelope<Customer>>(`/v1/customers/${id}`, {
    method: "PUT",
    body: { notes },
  });
  return data;
}

export async function getCustomerOrders(id: number): Promise<Paginated<Order>> {
  return adminFetch<Paginated<Order>>(`/v1/orders${toQueryString({ filter: { customer_id: id }, sort: "-created_at", per_page: 20 })}`);
}

/** The structured, append-only staff note log - distinct from the single `notes` field above. */
export async function getCustomerNotes(id: number): Promise<CustomerNote[]> {
  const { data } = await adminFetch<ApiEnvelope<CustomerNote[]>>(`/v1/customers/${id}/notes`);
  return data;
}

export async function addCustomerNote(id: number, body: string): Promise<CustomerNote> {
  const { data } = await adminFetch<ApiEnvelope<CustomerNote>>(`/v1/customers/${id}/notes`, {
    method: "POST",
    body: { body },
  });
  return data;
}

export async function getCustomerTags(): Promise<CustomerTag[]> {
  const { data } = await adminFetch<ApiEnvelope<CustomerTag[]>>("/v1/customer-tags");
  return data;
}

export async function createCustomerTag(name: string, color?: string): Promise<CustomerTag> {
  const { data } = await adminFetch<ApiEnvelope<CustomerTag>>("/v1/customer-tags", {
    method: "POST",
    body: { name, color },
  });
  return data;
}

export async function deleteCustomerTag(id: number): Promise<void> {
  await adminFetch<void>(`/v1/customer-tags/${id}`, { method: "DELETE" });
}

export async function attachCustomerTag(customerId: number, tagId: number): Promise<CustomerTag[]> {
  const { data } = await adminFetch<ApiEnvelope<CustomerTag[]>>(`/v1/customers/${customerId}/tags/${tagId}`, { method: "POST" });
  return data;
}

export async function detachCustomerTag(customerId: number, tagId: number): Promise<CustomerTag[]> {
  const { data } = await adminFetch<ApiEnvelope<CustomerTag[]>>(`/v1/customers/${customerId}/tags/${tagId}`, { method: "DELETE" });
  return data;
}
