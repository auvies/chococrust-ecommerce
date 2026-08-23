import type { Metadata } from "next";
import { notFound, redirect } from "next/navigation";
import { getProduct } from "@/lib/api/catalog";
import { getSeoMetadata } from "@/lib/api/content";
import { getProductReviews } from "@/lib/api/reviews";
import { ApiError } from "@/lib/api/client";
import { logger } from "@/lib/logger";
import { buildMetadata } from "@/lib/seo";
import { categoryHref, productHref } from "@/lib/routes";
import { Gallery } from "@/components/product/Gallery";
import { VariantSelector } from "@/components/product/VariantSelector";
import { ReviewList } from "@/components/product/ReviewList";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";
import { Container } from "@/components/ui/Container";
import type { Product, Review } from "@/types/api";

type Params = { id: string; slug: string };

async function loadProduct(id: string): Promise<Product> {
  const numericId = Number(id);
  if (!Number.isInteger(numericId) || numericId <= 0) notFound();

  try {
    return await getProduct(numericId);
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) notFound();
    throw error;
  }
}

export async function generateMetadata({ params }: { params: Promise<Params> }): Promise<Metadata> {
  const { id } = await params;
  const product = await loadProduct(id);
  const seo = await getSeoMetadata("product", product.id).catch(() => null);

  return buildMetadata(seo, {
    title: product.name,
    description: product.short_description ?? product.description,
    path: productHref(product),
    image: product.media.find((m) => m.is_primary)?.url ?? product.media[0]?.url,
  });
}

export default async function ProductPage({ params }: { params: Promise<Params> }) {
  const { id, slug } = await params;
  const product = await loadProduct(id);

  // Canonicalize: the backend resolves by id alone, so an old/wrong slug in
  // the URL still loads the right product — redirect to the current one.
  if (product.slug !== slug) {
    redirect(productHref(product));
  }

  let reviews: Review[] = [];
  let reviewTotal = 0;
  try {
    const result = await getProductReviews(product.id, { per_page: 10, sort: "-created_at" });
    reviews = result.data;
    reviewTotal = result.meta.total;
  } catch (error) {
    logger.error("Failed to load product reviews", { productId: product.id, message: (error as Error).message });
  }

  const category = product.categories[0];

  return (
    <main className="flex-1 py-6 sm:py-10">
      <Container className="flex flex-col gap-8">
        <Breadcrumbs
          items={[
            { label: "Home", href: "/" },
            ...(category ? [{ label: category.name, href: categoryHref(category) }] : []),
            { label: product.name },
          ]}
        />

        <div className="grid grid-cols-1 gap-8 lg:grid-cols-2">
          <Gallery media={product.media} productName={product.name} />

          <div className="flex flex-col gap-4">
            {product.brand ? (
              <span className="text-xs font-medium uppercase tracking-wide text-stone-500 dark:text-stone-400">
                {product.brand}
              </span>
            ) : null}
            <h1 className="text-2xl font-semibold tracking-tight text-stone-900 dark:text-stone-100 sm:text-3xl">
              {product.name}
            </h1>
            {product.short_description ? (
              <p className="text-sm text-stone-600 dark:text-stone-400">{product.short_description}</p>
            ) : null}
            <VariantSelector product={product} />
          </div>
        </div>

        {product.description ? (
          <section>
            <h2 className="mb-2 text-lg font-semibold text-stone-900 dark:text-stone-100">Description</h2>
            <p className="whitespace-pre-line text-sm text-stone-600 dark:text-stone-400">{product.description}</p>
          </section>
        ) : null}

        <section>
          <h2 className="mb-4 text-lg font-semibold text-stone-900 dark:text-stone-100">Reviews</h2>
          <ReviewList reviews={reviews} total={reviewTotal} ratingAverage={product.rating_average} />
        </section>
      </Container>
    </main>
  );
}
