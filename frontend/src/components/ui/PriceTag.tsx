import { discountPercent, formatPrice } from "@/lib/format";

export function PriceTag({
  price,
  compareAtPrice,
  currency,
}: {
  price: number;
  compareAtPrice?: number | null;
  currency: string;
}) {
  const hasDiscount = compareAtPrice != null && compareAtPrice > price;
  const percent = hasDiscount ? discountPercent(price, compareAtPrice) : 0;

  return (
    <div className="flex flex-wrap items-baseline gap-2">
      <span className="font-semibold text-stone-900 dark:text-stone-100">
        {formatPrice(price, currency)}
      </span>
      {hasDiscount ? (
        <>
          <span className="text-sm text-stone-500 line-through dark:text-stone-400">
            {formatPrice(compareAtPrice, currency)}
          </span>
          <span className="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-400">
            -{percent}%
          </span>
        </>
      ) : null}
    </div>
  );
}
