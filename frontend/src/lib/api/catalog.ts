import { apiFetch } from "@/lib/api/client";
import { toQueryString, type ListQueryParams } from "@/lib/api/query";
import type { ApiEnvelope, Category, Paginated, Product } from "@/types/api";

export async function getCategories(params: ListQueryParams = {}): Promise<Paginated<Category>> {
  return apiFetch<Paginated<Category>>(`/v1/categories${toQueryString(params)}`, {
    cache: "no-store",
  });
}

export async function getCategory(id: number): Promise<Category> {
  const { data } = await apiFetch<ApiEnvelope<Category>>(`/v1/categories/${id}`, {
    cache: "no-store",
  });
  return data;
}

export async function getProducts(params: ListQueryParams = {}): Promise<Paginated<Product>> {
  return apiFetch<Paginated<Product>>(`/v1/products${toQueryString(params)}`, {
    cache: "no-store",
  });
}

export async function getProduct(id: number): Promise<Product> {
  const { data } = await apiFetch<ApiEnvelope<Product>>(`/v1/products/${id}`, {
    cache: "no-store",
  });
  return data;
}

/**
 * Real sales-ranked best sellers (summed order quantity), with optional
 * admin override - see `ProductController::bestSellers` on the backend.
 */
export async function getBestSellers(limit = 8): Promise<Product[]> {
  const { data } = await apiFetch<ApiEnvelope<Product[]>>(`/v1/products/best-sellers?limit=${limit}`, {
    cache: "no-store",
  });
  return data;
}
