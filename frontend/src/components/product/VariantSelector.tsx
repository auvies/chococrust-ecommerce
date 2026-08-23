"use client";

import { useState } from "react";
import { PriceTag } from "@/components/ui/PriceTag";
import { useCart } from "@/lib/cart";
import { primaryImage } from "@/lib/product";
import type { Product } from "@/types/api";

/**
 * Variant selection (size/flavor), quantity, an optional per-item
 * customization note (e.g. a cake message), and the actual "Add to cart"
 * action — cart is client-only (no backend cart table, Phase 08), so this
 * writes straight into `useCart()`. Checkout re-prices everything from the
 * variant id server-side regardless of what's shown here.
 */
export function VariantSelector({ product }: { product: Product }) {
  const variants = product.variants;
  const active = variants.filter((v) => v.is_active);
  const initial = active.find((v) => v.is_default) ?? active[0] ?? variants[0];
  const [selectedId, setSelectedId] = useState(initial?.id);
  const [quantity, setQuantity] = useState(1);
  const [note, setNote] = useState("");
  const [added, setAdded] = useState(false);
  const { addItem } = useCart();

  const selected = variants.find((v) => v.id === selectedId) ?? initial;

  if (!selected) return null;

  function handleAddToCart() {
    if (!selected) return;
    addItem(
      {
        productVariantId: selected.id,
        productId: product.id,
        productSlug: product.slug,
        productName: product.name,
        variantName: selected.name,
        sku: selected.sku,
        price: selected.price,
        currency: selected.currency,
        imageUrl: primaryImage(product)?.url ?? null,
      },
      quantity,
      note.trim(),
    );
    setAdded(true);
    setQuantity(1);
    setNote("");
  }

  return (
    <div className="flex flex-col gap-3">
      <PriceTag price={selected.price} compareAtPrice={selected.compare_at_price} currency={selected.currency} />

      {active.length > 1 ? (
        <div role="radiogroup" aria-label="Variant" className="flex flex-wrap gap-2">
          {active.map((variant) => (
            <button
              key={variant.id}
              type="button"
              role="radio"
              aria-checked={variant.id === selectedId}
              onClick={() => {
                setSelectedId(variant.id);
                setAdded(false);
              }}
              className={`rounded-full border px-3 py-1.5 text-sm ${
                variant.id === selectedId
                  ? "border-amber-700 bg-amber-700 text-white"
                  : "border-stone-300 text-stone-700 hover:border-amber-700 dark:border-stone-700 dark:text-stone-300"
              }`}
            >
              {variant.name}
            </button>
          ))}
        </div>
      ) : null}

      <p className="text-xs text-stone-500 dark:text-stone-500">SKU: {selected.sku}</p>

      <label className="flex flex-col gap-1 text-sm text-stone-700 dark:text-stone-300">
        Customization / special instructions (optional)
        <input
          type="text"
          value={note}
          onChange={(e) => {
            setNote(e.target.value);
            setAdded(false);
          }}
          placeholder="e.g. “Happy Birthday Ali!”"
          maxLength={500}
          className="rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-900"
        />
      </label>

      <div className="flex items-center gap-3">
        <div className="flex items-center gap-2" role="group" aria-label="Quantity">
          <button
            type="button"
            onClick={() => {
              setQuantity((q) => Math.max(1, q - 1));
              setAdded(false);
            }}
            aria-label="Decrease quantity"
            className="h-9 w-9 rounded-md border border-stone-300 text-sm dark:border-stone-700"
          >
            −
          </button>
          <span className="w-6 text-center text-sm">{quantity}</span>
          <button
            type="button"
            onClick={() => {
              setQuantity((q) => Math.min(100, q + 1));
              setAdded(false);
            }}
            aria-label="Increase quantity"
            className="h-9 w-9 rounded-md border border-stone-300 text-sm dark:border-stone-700"
          >
            +
          </button>
        </div>

        <button
          type="button"
          onClick={handleAddToCart}
          className="flex-1 rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white dark:bg-amber-700"
        >
          {added ? "Added to cart" : "Add to cart"}
        </button>
      </div>
    </div>
  );
}
