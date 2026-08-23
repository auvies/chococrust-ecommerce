import type { Metadata } from "next";
import { Suspense } from "react";
import { getCategories, getProducts } from "@/lib/api/catalog";
import { emptyPage } from "@/lib/api/query";
import { logger } from "@/lib/logger";
import { ListControls } from "@/components/category/ListControls";
import { ProductGrid } from "@/components/product/ProductGrid";
import { Pagination } from "@/components/ui/Pagination";
import { Container } from "@/components/ui/Container";
import type { Category, Paginated, Product } from "@/types/api";

type Search = { q?: string; sort?: string; page?: string; category?: string; min_price?: string; max_price?: string };

export async function generateMetadata({ searchParams }: { searchParams: Promise<Search> }): Promise<Metadata> {
  const { q } = await searchParams;
  return { title: q ? `Search: ${q}` : "All Products" };
}

/**
 * Doubles as the catalog's general browse/filter surface (search term,
 * category — including subcategories, and price range are all optional and
 * combinable) and as the classic "type something, get results" search page.
 * With no query at all it lists every active product, which is what the
 * homepage's "All Products" section links out to.
 */
export default async function SearchPage({ searchParams }: { searchParams: Promise<Search> }) {
  const sp = await searchParams;
  const query = sp.q?.trim() || "";
  const sort = sp.sort || "name";
  const page = Number(sp.page) || 1;
  const categoryId = sp.category ? Number(sp.category) : undefined;
  const minPrice = sp.min_price ? Number(sp.min_price) : undefined;
  const maxPrice = sp.max_price ? Number(sp.max_price) : undefined;

  let categories: Category[] = [];
  try {
    const result = await getCategories({ filter: { is_active: true }, sort: "sort_order", per_page: 50 });
    categories = result.data;
  } catch (error) {
    logger.error("Failed to load filter categories", { message: (error as Error).message });
  }

  let products: Paginated<Product> = emptyPage(12);
  try {
    products = await getProducts({
      filter: { status: "active", category_id: categoryId },
      search: query || undefined,
      sort,
      page,
      per_page: 12,
      min_price: minPrice,
      max_price: maxPrice,
    });
  } catch (error) {
    logger.error("Search failed", { query, message: (error as Error).message });
  }

  const hasFilters = Boolean(query || categoryId || minPrice || maxPrice);

  return (
    <main className="flex-1 py-6 sm:py-10">
      <Container className="flex flex-col gap-6">
        <h1 className="text-2xl font-semibold tracking-tight text-stone-900 dark:text-stone-100 sm:text-3xl">
          {query ? `Search results for "${query}"` : "All Products"}
        </h1>

        <Suspense fallback={null}>
          <ListControls
            initialSearch={query}
            categories={categories}
            initialCategory={sp.category ?? ""}
            initialMinPrice={sp.min_price ?? ""}
            initialMaxPrice={sp.max_price ?? ""}
          />
        </Suspense>

        <ProductGrid
          products={products.data}
          emptyMessage={hasFilters ? "No products matched your filters. Try adjusting them." : "No products available yet."}
        />
        <Pagination
          meta={products.meta}
          basePath="/search"
          searchParams={{ q: query, sort, category: sp.category, min_price: sp.min_price, max_price: sp.max_price }}
        />
      </Container>
    </main>
  );
}
