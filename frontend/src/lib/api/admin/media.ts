import { apiFetch } from "@/lib/api/client";
import { uploadFormData } from "@/lib/api/admin/upload";
import type { ProductMedia } from "@/types/api";

export async function uploadMedia(payload: {
  file: File;
  productId: number;
  productVariantId?: number;
  altText?: string;
  isPrimary?: boolean;
}): Promise<ProductMedia> {
  const form = new FormData();
  form.set("file", payload.file);
  form.set("product_id", String(payload.productId));
  if (payload.productVariantId) form.set("product_variant_id", String(payload.productVariantId));
  if (payload.altText) form.set("alt_text", payload.altText);
  if (payload.isPrimary) form.set("is_primary", "1");

  return uploadFormData<ProductMedia>("/v1/media", form);
}

export async function deleteMedia(id: number): Promise<void> {
  await apiFetch<void>(`/v1/media/${id}`, { method: "DELETE" });
}
