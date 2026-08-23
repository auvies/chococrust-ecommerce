import { apiFetch } from "@/lib/api/client";
import { toQueryString, type ListQueryParams } from "@/lib/api/query";
import type { Paginated, Review } from "@/types/api";

/** Public endpoint only ever returns approved reviews (server-enforced). */
export async function getProductReviews(
  productId: number,
  params: ListQueryParams = {},
): Promise<Paginated<Review>> {
  return apiFetch<Paginated<Review>>(
    `/v1/products/${productId}/reviews${toQueryString(params)}`,
    { cache: "no-store" },
  );
}
