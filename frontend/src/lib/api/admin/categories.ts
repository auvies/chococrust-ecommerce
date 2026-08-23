import { adminFetch } from "@/lib/api/admin/client";
import { uploadFormData } from "@/lib/api/admin/upload";
import type { ApiEnvelope, Category } from "@/types/api";

export interface CategoryPayload {
  parent_id?: number | null;
  name: string;
  slug: string;
  description?: string | null;
  image_url?: string | null;
  alt_text?: string | null;
  is_active?: boolean;
  sort_order?: number;
}

export async function createCategory(payload: CategoryPayload): Promise<Category> {
  const { data } = await adminFetch<ApiEnvelope<Category>>("/v1/categories", { method: "POST", body: payload });
  return data;
}

export async function updateCategory(id: number, payload: Partial<CategoryPayload>): Promise<Category> {
  const { data } = await adminFetch<ApiEnvelope<Category>>(`/v1/categories/${id}`, { method: "PUT", body: payload });
  return data;
}

export async function deleteCategory(id: number): Promise<void> {
  await adminFetch<void>(`/v1/categories/${id}`, { method: "DELETE" });
}

/** Secure file upload (server-validated image, size-capped, safely renamed) - not a raw URL field. */
export async function uploadCategoryImage(id: number, file: File, altText?: string): Promise<Category> {
  const form = new FormData();
  form.set("file", file);
  if (altText) form.set("alt_text", altText);

  return uploadFormData<Category>(`/v1/categories/${id}/image`, form);
}
