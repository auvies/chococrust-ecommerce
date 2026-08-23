import Link from "next/link";
import { PriceTag } from "@/components/ui/PriceTag";
import { productHref } from "@/lib/routes";
import { defaultVariant, primaryImage } from "@/lib/product";
import type { Product } from "@/types/api";

export function ProductCard({ product }: { product: Product }) {
  const variant = defaultVariant(product);
  const image = primaryImage(product);

  return (
    <Link
      href={productHref(product)}
      className="group flex flex-col overflow-hidden rounded-xl border border-stone-200 bg-white transition hover:shadow-md dark:border-stone-800 dark:bg-stone-900"
    >
      <div className="aspect-square w-full overflow-hidden bg-stone-100 dark:bg-stone-800">
        {image ? (
          // Product image URLs come from whichever object-storage/CDN
          // domain is provisioned later (README "Backend Architecture" TBD)
          // — an <img> avoids hardcoding next/image remotePatterns now.
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={image.url}
            alt={image.alt_text ?? product.name}
            loading="lazy"
            className="h-full w-full object-cover transition group-hover:scale-105"
          />
        ) : (
          <div className="flex h-full w-full items-center justify-center text-sm text-stone-400">
            No image
          </div>
        )}
      </div>
      <div className="flex flex-1 flex-col gap-2 p-3 sm:p-4">
        {product.brand ? (
          <span className="text-xs font-medium uppercase tracking-wide text-stone-500 dark:text-stone-400">
            {product.brand}
          </span>
        ) : null}
        <h3 className="line-clamp-2 text-sm font-medium text-stone-900 dark:text-stone-100 sm:text-base">
          {product.name}
        </h3>
        {variant ? (
          <div className="mt-auto pt-1">
            <PriceTag price={variant.price} compareAtPrice={variant.compare_at_price} currency={variant.currency} />
          </div>
        ) : null}
      </div>
    </Link>
  );
}
