import { adminFetch } from "@/lib/api/admin/client";
import type { ApiEnvelope, Product, ProductVariant } from "@/types/api";

export interface VariantPayload {
  sku: string;
  name: string;
  price: number;
  compare_at_price?: number | null;
  currency?: string;
  weight_grams?: number | null;
  attributes?: Record<string, unknown> | null;
  is_default?: boolean;
  is_active?: boolean;
}

export interface ProductPayload {
  category_id?: number | null;
  name: string;
  slug: string;
  description?: string | null;
  short_description?: string | null;
  brand?: string | null;
  status?: "draft" | "active" | "archived";
  is_featured?: boolean;
  variants?: VariantPayload[];
}

export async function createProduct(payload: ProductPayload): Promise<Product> {
  const { data } = await adminFetch<ApiEnvelope<Product>>("/v1/products", { method: "POST", body: payload });
  return data;
}

/** UpdateProductRequest doesn't accept `variants` — those go through the dedicated variant endpoints below. */
export type ProductCoreFields = Omit<ProductPayload, "variants">;

export async function updateProduct(id: number, payload: Partial<ProductCoreFields>): Promise<Product> {
  const { data } = await adminFetch<ApiEnvelope<Product>>(`/v1/products/${id}`, { method: "PUT", body: payload });
  return data;
}

export async function deleteProduct(id: number): Promise<void> {
  await adminFetch<void>(`/v1/products/${id}`, { method: "DELETE" });
}

export async function createVariant(productId: number, payload: VariantPayload): Promise<ProductVariant> {
  const { data } = await adminFetch<ApiEnvelope<ProductVariant>>(`/v1/products/${productId}/variants`, {
    method: "POST",
    body: payload,
  });
  return data;
}

export async function updateVariant(
  productId: number,
  variantId: number,
  payload: Partial<VariantPayload>,
): Promise<ProductVariant> {
  const { data } = await adminFetch<ApiEnvelope<ProductVariant>>(`/v1/products/${productId}/variants/${variantId}`, {
    method: "PUT",
    body: payload,
  });
  return data;
}

export async function deleteVariant(productId: number, variantId: number): Promise<void> {
  await adminFetch<void>(`/v1/products/${productId}/variants/${variantId}`, { method: "DELETE" });
}
