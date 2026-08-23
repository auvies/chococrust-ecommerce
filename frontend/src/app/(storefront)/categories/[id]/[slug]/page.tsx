import type { Metadata } from "next";
import { Suspense } from "react";
import { notFound, redirect } from "next/navigation";
import { getCategory, getProducts } from "@/lib/api/catalog";
import { getSeoMetadata } from "@/lib/api/content";
import { emptyPage } from "@/lib/api/query";
import { ApiError } from "@/lib/api/client";
import { logger } from "@/lib/logger";
import { buildMetadata } from "@/lib/seo";
import { categoryHref } from "@/lib/routes";
import { CategoryGrid } from "@/components/home/CategoryGrid";
import { ListControls } from "@/components/category/ListControls";
import { ProductGrid } from "@/components/product/ProductGrid";
import { Pagination } from "@/components/ui/Pagination";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";
import { Container } from "@/components/ui/Container";
import type { Category, Paginated, Product } from "@/types/api";

type Params = { id: string; slug: string };
type Search = { sort?: string; q?: string; page?: string };

async function loadCategory(id: string): Promise<Category> {
  const numericId = Number(id);
  if (!Number.isInteger(numericId) || numericId <= 0) notFound();

  try {
    return await getCategory(numericId);
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  }
}

export async function generateMetadata({ params }: { params: Promise<Params> }): Promise<Metadata> {
  const { id } = await params;
  const category = await loadCategory(id);
  const seo = await getSeoMetadata("category", category.id).catch(() => null);

  return buildMetadata(seo, {
    title: category.name,
    description: category.description,
    path: categoryHref(category),
    image: category.image_url,
  });
}

export default async function CategoryPage({
  params,
  searchParams,
}: {
  params: Promise<Params>;
  searchParams: Promise<Search>;
}) {
  const { id, slug } = await params;
  const sp = await searchParams;
  const category = await loadCategory(id);

  if (category.slug !== slug) {
    redirect(categoryHref(category));
  }

  const page = Number(sp.page) || 1;
  const sort = sp.sort || "name";
  const query = sp.q || "";

  let products: Paginated<Product> = emptyPage(12);
  try {
    products = await getProducts({
      filter: { category_id: category.id, status: "active" },
      sort,
      search: query || undefined,
      page,
      per_page: 12,
    });
  } catch (error) {
    logger.error("Failed to load category products", { categoryId: category.id, message: (error as Error).message });
  }

  return (
    <main className="flex-1 py-6 sm:py-10">
      <Container className="flex flex-col gap-6">
        <Breadcrumbs items={[{ label: "Home", href: "/" }, { label: category.name }]} />

        <div>
          <h1 className="text-2xl font-semibold tracking-tight text-stone-900 dark:text-stone-100 sm:text-3xl">
            {category.name}
          </h1>
          {category.description ? (
            <p className="mt-2 max-w-2xl text-sm text-stone-600 dark:text-stone-400">{category.description}</p>
          ) : null}
        </div>

        {category.children.length > 0 ? <CategoryGrid categories={category.children} /> : null}

        <Suspense fallback={null}>
          <ListControls initialSearch={query} />
        </Suspense>

        <ProductGrid products={products.data} emptyMessage="No products in this category yet." />

        <Pagination meta={products.meta} basePath={categoryHref(category)} searchParams={{ sort, q: query }} />
      </Container>
    </main>
  );
}
