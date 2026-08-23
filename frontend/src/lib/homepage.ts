import { getBestSellers as fetchBestSellers, getProducts } from "@/lib/api/catalog";
import { getProductReviews } from "@/lib/api/reviews";
import { bestDiscount, type Discount } from "@/lib/product";
import { logger } from "@/lib/logger";
import type { Product, Review } from "@/types/api";

/**
 * Best sellers come from the backend's `/products/best-sellers` endpoint,
 * which ranks by real sales data (summed order quantity, excluding
 * cancelled/refunded orders) with an optional admin override - see
 * `ProductController::bestSellers` (Phase 07). This replaced the earlier
 * review-count proxy used before sales data existed to rank against.
 *
 * Offers and testimonials still share ONE `/products` fetch as their
 * candidate pool, and only the testimonials lookup pays the cost of a
 * per-product review lookup (bounded to `reviewPoolSize` products) — the
 * general API rate limit (CLAUDE.md §14) applies per visitor, so the
 * homepage is kept to a small, fixed number of requests regardless of
 * catalog size.
 */

export interface Testimonial extends Review {
  productId: number;
  productName: string;
  productSlug: string;
}

export interface Offer {
  product: Product;
  discount: Discount;
}

export interface HomepageProductData {
  bestSellers: Product[];
  testimonials: Testimonial[];
  offers: Offer[];
}

const EMPTY: HomepageProductData = { bestSellers: [], testimonials: [], offers: [] };

export async function getFeaturedProducts(limit = 8): Promise<Product[]> {
  try {
    const { data } = await getProducts({
      filter: { is_featured: true, status: "active" },
      sort: "name",
      per_page: limit,
    });
    return data;
  } catch (error) {
    logger.error("Failed to load featured products", { message: (error as Error).message });
    return [];
  }
}

export async function getBestSellers(limit = 6): Promise<Product[]> {
  try {
    return await fetchBestSellers(limit);
  } catch (error) {
    logger.error("Failed to load best sellers", { message: (error as Error).message });
    return [];
  }
}

export async function getHomepageProductData(
  poolSize = 16,
  reviewPoolSize = 6,
  limitPerSection = 6,
): Promise<HomepageProductData> {
  try {
    const [{ data: pool }, bestSellers] = await Promise.all([
      getProducts({ filter: { status: "active" }, per_page: poolSize }),
      getBestSellers(limitPerSection),
    ]);

    const offers = pool
      .map((product) => ({ product, discount: bestDiscount(product) }))
      .filter((entry): entry is Offer => entry.discount !== null)
      .sort((a, b) => b.discount.percent - a.discount.percent)
      .slice(0, limitPerSection);

    const reviewCandidates = pool.slice(0, reviewPoolSize);
    const withReviews = await Promise.all(
      reviewCandidates.map(async (product) => {
        try {
          const reviews = await getProductReviews(product.id, { per_page: 3, sort: "-rating" });
          return { product, reviews: reviews.data };
        } catch {
          return { product, reviews: [] as Review[] };
        }
      }),
    );

    const testimonials: Testimonial[] = withReviews
      .flatMap((entry) =>
        entry.reviews
          .filter((review) => review.rating >= 4 && (review.title || review.body))
          .map((review) => ({
            ...review,
            productId: entry.product.id,
            productName: entry.product.name,
            productSlug: entry.product.slug,
          })),
      )
      .sort((a, b) => b.rating - a.rating)
      .slice(0, limitPerSection);

    return { bestSellers, testimonials, offers };
  } catch (error) {
    logger.error("Failed to load homepage product data", { message: (error as Error).message });
    return EMPTY;
  }
}
