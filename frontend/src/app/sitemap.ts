import type { MetadataRoute } from "next";
import { getCategories, getProducts } from "@/lib/api/catalog";
import { categoryHref, productHref } from "@/lib/routes";
import { env } from "@/lib/env";
import { logger } from "@/lib/logger";

const STATIC_ROUTES = ["/", "/categories", "/about", "/contact", "/faq"];

// Avoids Next attempting (and failing) a static prerender of this route at
// build time against a backend that may not be running yet.
export const dynamic = "force-dynamic";

/**
 * Built from live catalog data at request time (not a fixed list) — bounded
 * to keep the response reasonably fast; a larger catalog would need a
 * paginated/multi-sitemap setup, not needed at this scale yet.
 */
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const entries: MetadataRoute.Sitemap = STATIC_ROUTES.map((path) => ({
    url: `${env.siteUrl}${path}`,
    lastModified: new Date(),
  }));

  try {
    const [categories, products] = await Promise.all([
      getCategories({ filter: { is_active: true }, per_page: 100 }),
      getProducts({ filter: { status: "active" }, per_page: 100 }),
    ]);

    for (const category of categories.data) {
      entries.push({ url: `${env.siteUrl}${categoryHref(category)}`, lastModified: category.updated_at });
    }
    for (const product of products.data) {
      entries.push({ url: `${env.siteUrl}${productHref(product)}`, lastModified: product.updated_at });
    }
  } catch (error) {
    logger.error("Failed to build dynamic sitemap entries", { message: (error as Error).message });
  }

  return entries;
}
