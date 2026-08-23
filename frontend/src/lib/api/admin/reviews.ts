import { adminFetch } from "@/lib/api/admin/client";
import { toQueryString, type ListQueryParams } from "@/lib/api/query";
import type { ApiEnvelope, Paginated, Review } from "@/types/api";

export async function getAllReviews(params: ListQueryParams = {}): Promise<Paginated<Review>> {
  return adminFetch<Paginated<Review>>(`/v1/reviews${toQueryString(params)}`);
}

export async function approveReview(id: number): Promise<Review> {
  const { data } = await adminFetch<ApiEnvelope<Review>>(`/v1/reviews/${id}/approve`, { method: "PATCH" });
  return data;
}

export async function rejectReview(id: number): Promise<void> {
  await adminFetch<void>(`/v1/reviews/${id}/reject`, { method: "DELETE" });
}
