"use client";

import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from "react";

/**
 * There is no backend cart table (Phase 02 schema) — checkout goes straight
 * from a client-supplied `items[]` array to a server-priced order
 * (`OrderService::priceLines`), so the cart itself is purely a client-side,
 * localStorage-backed convenience. Nothing here is trusted at checkout time:
 * price/name/etc. are only for display, and the backend always re-resolves
 * the real price from the variant id (CLAUDE.md §1).
 */
export interface CartItem {
  productVariantId: number;
  productId: number;
  productSlug: string;
  productName: string;
  variantName: string;
  sku: string;
  price: number;
  currency: string;
  imageUrl: string | null;
  quantity: number;
  /** Free-text per-item customization (e.g. a cake message) — mirrors OrderItem.customization_note. */
  customizationNote: string;
}

interface CartContextValue {
  items: CartItem[];
  addItem: (item: Omit<CartItem, "quantity" | "customizationNote">, quantity?: number, customizationNote?: string) => void;
  updateQuantity: (productVariantId: number, quantity: number) => void;
  updateCustomizationNote: (productVariantId: number, note: string) => void;
  removeItem: (productVariantId: number) => void;
  clear: () => void;
  subtotal: number;
  itemCount: number;
}

const STORAGE_KEY = "cc_cart_v1";
/** Mirrors StoreOrderRequest's `items.*.quantity` max:100 rule — no point letting the cart drift past what checkout will ever accept. */
const MAX_QUANTITY = 100;

const CartContext = createContext<CartContextValue | null>(null);

function readStoredCart(): CartItem[] {
  if (typeof window === "undefined") return [];
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    const parsed: unknown = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed) ? (parsed as CartItem[]) : [];
  } catch {
    return [];
  }
}

export function CartProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<CartItem[]>([]);
  const [hydrated, setHydrated] = useState(false);

  // localStorage doesn't exist during SSR — hydrating in an effect (not
  // useState's initializer) avoids a server/client markup mismatch on the
  // very first render. The one-time mount-time setState this rule warns
  // about is unavoidable for any "read external storage after mount" case.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setItems(readStoredCart());
    setHydrated(true);
  }, []);

  useEffect(() => {
    if (!hydrated) return;
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
  }, [items, hydrated]);

  function addItem(item: Omit<CartItem, "quantity" | "customizationNote">, quantity = 1, customizationNote = "") {
    setItems((current) => {
      const existing = current.find((i) => i.productVariantId === item.productVariantId);
      if (existing) {
        return current.map((i) =>
          i.productVariantId === item.productVariantId
            ? {
                ...i,
                quantity: Math.min(i.quantity + quantity, MAX_QUANTITY),
                customizationNote: customizationNote || i.customizationNote,
              }
            : i,
        );
      }
      return [...current, { ...item, quantity: Math.min(Math.max(quantity, 1), MAX_QUANTITY), customizationNote }];
    });
  }

  function updateQuantity(productVariantId: number, quantity: number) {
    setItems((current) =>
      quantity <= 0
        ? current.filter((i) => i.productVariantId !== productVariantId)
        : current.map((i) =>
            i.productVariantId === productVariantId ? { ...i, quantity: Math.min(quantity, MAX_QUANTITY) } : i,
          ),
    );
  }

  function updateCustomizationNote(productVariantId: number, note: string) {
    setItems((current) =>
      current.map((i) => (i.productVariantId === productVariantId ? { ...i, customizationNote: note } : i)),
    );
  }

  function removeItem(productVariantId: number) {
    setItems((current) => current.filter((i) => i.productVariantId !== productVariantId));
  }

  function clear() {
    setItems([]);
  }

  const subtotal = useMemo(() => items.reduce((sum, i) => sum + i.price * i.quantity, 0), [items]);
  const itemCount = useMemo(() => items.reduce((sum, i) => sum + i.quantity, 0), [items]);

  return (
    <CartContext.Provider
      value={{ items, addItem, updateQuantity, updateCustomizationNote, removeItem, clear, subtotal, itemCount }}
    >
      {children}
    </CartContext.Provider>
  );
}

export function useCart(): CartContextValue {
  const ctx = useContext(CartContext);
  if (!ctx) throw new Error("useCart must be used within CartProvider");
  return ctx;
}
