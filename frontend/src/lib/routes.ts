import type { Category, Product } from "@/types/api";

/**
 * The backend resolves `{product}`/`{category}` route-model-bindings by
 * numeric id only (no `slug` route key configured) — see
 * `backend/routes/api/catalog.php`. The frontend still publishes the slug
 * in the URL for readability/SEO, but always fetches by the id segment.
 */
export function productHref(product: Pick<Product, "id" | "slug">): string {
  return `/products/${product.id}/${product.slug}`;
}

export function categoryHref(category: Pick<Category, "id" | "slug">): string {
  return `/categories/${category.id}/${category.slug}`;
}
