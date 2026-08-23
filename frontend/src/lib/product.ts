import type { Product, ProductMedia, ProductVariant } from "@/types/api";

export function defaultVariant(product: Product): ProductVariant | undefined {
  return (
    product.variants.find((v) => v.is_default && v.is_active) ??
    product.variants.find((v) => v.is_active) ??
    product.variants[0]
  );
}

export function primaryImage(product: Product): ProductMedia | undefined {
  const images = product.media.filter((m) => m.type === "image");
  return images.find((m) => m.is_primary) ?? [...images].sort((a, b) => a.sort_order - b.sort_order)[0];
}

export interface Discount {
  variant: ProductVariant;
  percent: number;
}

/** The variant with the largest markdown, if any variant has `compare_at_price > price`. */
export function bestDiscount(product: Product): Discount | null {
  let best: Discount | null = null;

  for (const variant of product.variants) {
    if (variant.compare_at_price === null || variant.compare_at_price <= variant.price) continue;
    const percent = Math.round(
      ((variant.compare_at_price - variant.price) / variant.compare_at_price) * 100,
    );
    if (!best || percent > best.percent) best = { variant, percent };
  }

  return best;
}
