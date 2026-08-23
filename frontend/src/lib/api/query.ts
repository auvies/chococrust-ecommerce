import type { Paginated } from "@/types/api";

/**
 * Builds `?filter[col]=x&sort=...&search=...` query strings matching the
 * shape `App\Support\Api\ApiQuery` (backend) expects on every index()
 * endpoint. One helper instead of hand-building strings at each call site.
 */

export interface ListQueryParams {
  /** An array value becomes `filter[col][]=a&filter[col][]=b` - ApiQuery's `whereIn` on the backend. */
  filter?: Record<string, string | number | boolean | (string | number)[] | undefined | null>;
  sort?: string;
  search?: string;
  per_page?: number;
  page?: number;
  /** Not a `filter[]` column - price lives on variants, so the backend reads these as plain query params. */
  min_price?: number;
  max_price?: number;
}

export function toQueryString(params: ListQueryParams = {}): string {
  const search = new URLSearchParams();

  if (params.filter) {
    for (const [key, value] of Object.entries(params.filter)) {
      if (value === undefined || value === null || value === "") continue;
      if (Array.isArray(value)) {
        for (const item of value) search.append(`filter[${key}][]`, String(item));
      } else {
        search.set(`filter[${key}]`, typeof value === "boolean" ? (value ? "1" : "0") : String(value));
      }
    }
  }

  if (params.sort) search.set("sort", params.sort);
  if (params.search) search.set("search", params.search);
  if (params.per_page) search.set("per_page", String(params.per_page));
  if (params.page) search.set("page", String(params.page));
  if (params.min_price !== undefined) search.set("min_price", String(params.min_price));
  if (params.max_price !== undefined) search.set("max_price", String(params.max_price));

  const qs = search.toString();
  return qs ? `?${qs}` : "";
}

/** Fallback shape for a failed/empty paginated list, so pages don't need bespoke empty literals. */
export function emptyPage<T>(perPage = 15): Paginated<T> {
  return {
    data: [],
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, from: null, last_page: 1, path: "", per_page: perPage, to: null, total: 0 },
  };
}
