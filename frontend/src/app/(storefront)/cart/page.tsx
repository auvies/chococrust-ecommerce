"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCart } from "@/lib/cart";
import { formatPrice } from "@/lib/format";
import { productHref } from "@/lib/routes";
import { Container } from "@/components/ui/Container";
import { EmptyState } from "@/components/ui/EmptyState";

export default function CartPage() {
  const { items, updateQuantity, updateCustomizationNote, removeItem, subtotal } = useCart();
  const router = useRouter();

  return (
    <main className="flex-1 py-6 sm:py-10">
      <Container className="flex flex-col gap-6">
        <h1 className="text-2xl font-semibold tracking-tight text-stone-900 dark:text-stone-100 sm:text-3xl">Your Cart</h1>

        {items.length === 0 ? (
          <EmptyState message="Your cart is empty. Browse the catalog to add something delicious." />
        ) : (
          <div className="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <div className="flex flex-col gap-4 lg:col-span-2">
              {items.map((item) => (
                <div
                  key={item.productVariantId}
                  className="flex flex-col gap-3 rounded-lg border border-stone-200 p-4 dark:border-stone-800 sm:flex-row"
                >
                  <div className="h-20 w-20 shrink-0 overflow-hidden rounded-md bg-stone-100 dark:bg-stone-900">
                    {item.imageUrl ? (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img src={item.imageUrl} alt={item.productName} loading="lazy" className="h-full w-full object-cover" />
                    ) : null}
                  </div>

                  <div className="flex flex-1 flex-col gap-2">
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <Link href={productHref({ id: item.productId, slug: item.productSlug })} className="font-medium text-stone-900 hover:underline dark:text-stone-100">
                          {item.productName}
                        </Link>
                        <p className="text-xs text-stone-500 dark:text-stone-400">
                          {item.variantName} · SKU {item.sku}
                        </p>
                      </div>
                      <button
                        type="button"
                        onClick={() => removeItem(item.productVariantId)}
                        className="shrink-0 text-xs font-medium text-red-600 hover:underline dark:text-red-400"
                      >
                        Remove
                      </button>
                    </div>

                    <label className="flex flex-col gap-1 text-xs text-stone-500 dark:text-stone-400">
                      Customization / special instructions (optional)
                      <input
                        type="text"
                        value={item.customizationNote}
                        onChange={(e) => updateCustomizationNote(item.productVariantId, e.target.value)}
                        placeholder="e.g. “Happy Birthday Ali!”"
                        maxLength={500}
                        className="rounded-md border border-stone-300 px-2 py-1 text-sm text-stone-900 dark:border-stone-700 dark:bg-stone-900 dark:text-stone-100"
                      />
                    </label>

                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2" role="group" aria-label={`Quantity for ${item.productName}`}>
                        <button
                          type="button"
                          onClick={() => updateQuantity(item.productVariantId, item.quantity - 1)}
                          aria-label="Decrease quantity"
                          className="h-7 w-7 rounded-md border border-stone-300 text-sm dark:border-stone-700"
                        >
                          −
                        </button>
                        <span className="w-6 text-center text-sm">{item.quantity}</span>
                        <button
                          type="button"
                          onClick={() => updateQuantity(item.productVariantId, item.quantity + 1)}
                          aria-label="Increase quantity"
                          className="h-7 w-7 rounded-md border border-stone-300 text-sm dark:border-stone-700"
                        >
                          +
                        </button>
                      </div>
                      <span className="font-medium text-stone-900 dark:text-stone-100">
                        {formatPrice(item.price * item.quantity, item.currency)}
                      </span>
                    </div>
                  </div>
                </div>
              ))}
            </div>

            <div className="flex h-fit flex-col gap-4 rounded-lg border border-stone-200 p-4 dark:border-stone-800">
              <div className="flex items-center justify-between text-sm text-stone-600 dark:text-stone-400">
                <span>Subtotal</span>
                <span className="font-semibold text-stone-900 dark:text-stone-100">{formatPrice(subtotal)}</span>
              </div>
              <p className="text-xs text-stone-500 dark:text-stone-500">
                Delivery fees and eligibility are calculated at checkout based on your address.
              </p>
              <button
                type="button"
                onClick={() => router.push("/checkout")}
                className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white dark:bg-amber-700"
              >
                Proceed to checkout
              </button>
            </div>
          </div>
        )}
      </Container>
    </main>
  );
}
