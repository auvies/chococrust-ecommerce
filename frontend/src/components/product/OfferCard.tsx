import Link from "next/link";
import { PriceTag } from "@/components/ui/PriceTag";
import { productHref } from "@/lib/routes";
import { primaryImage } from "@/lib/product";
import type { Offer } from "@/lib/homepage";

export function OfferCard({ offer }: { offer: Offer }) {
  const image = primaryImage(offer.product);
  const { variant } = offer.discount;

  return (
    <Link
      href={productHref(offer.product)}
      className="group flex flex-col overflow-hidden rounded-xl border border-amber-200 bg-white transition hover:shadow-md dark:border-amber-900 dark:bg-stone-900"
    >
      <div className="relative aspect-square w-full overflow-hidden bg-stone-100 dark:bg-stone-800">
        <span className="absolute left-2 top-2 z-10 rounded-full bg-amber-700 px-2 py-0.5 text-xs font-semibold text-white">
          -{offer.discount.percent}%
        </span>
        {image ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={image.url}
            alt={image.alt_text ?? offer.product.name}
            loading="lazy"
            className="h-full w-full object-cover transition group-hover:scale-105"
          />
        ) : (
          <div className="flex h-full w-full items-center justify-center text-sm text-stone-400">No image</div>
        )}
      </div>
      <div className="flex flex-1 flex-col gap-2 p-3 sm:p-4">
        <h3 className="line-clamp-2 text-sm font-medium text-stone-900 dark:text-stone-100 sm:text-base">
          {offer.product.name}
        </h3>
        <div className="mt-auto pt-1">
          <PriceTag price={variant.price} compareAtPrice={variant.compare_at_price} currency={variant.currency} />
        </div>
      </div>
    </Link>
  );
}
