import { describe, expect, it } from "vitest";
import { bestDiscount, defaultVariant, primaryImage } from "./product";
import type { Product } from "@/types/api";

function makeProduct(overrides: Partial<Product> = {}): Product {
  return {
    id: 1,
    category_id: null,
    name: "Chocolate Cake",
    slug: "chocolate-cake",
    description: null,
    short_description: null,
    brand: null,
    status: "active",
    is_featured: false,
    variants: [],
    media: [],
    categories: [],
    created_at: "2026-01-01T00:00:00Z",
    updated_at: "2026-01-01T00:00:00Z",
    ...overrides,
  };
}

describe("defaultVariant", () => {
  it("prefers the variant flagged is_default and active", () => {
    const product = makeProduct({
      variants: [
        { id: 1, sku: "A", name: "Small", price: 500, compare_at_price: null, currency: "PKR", weight_grams: null, attributes: null, is_default: false, is_active: true },
        { id: 2, sku: "B", name: "Large", price: 900, compare_at_price: null, currency: "PKR", weight_grams: null, attributes: null, is_default: true, is_active: true },
      ],
    });

    expect(defaultVariant(product)?.id).toBe(2);
  });

  it("falls back to the first active variant when none is default", () => {
    const product = makeProduct({
      variants: [
        { id: 1, sku: "A", name: "Small", price: 500, compare_at_price: null, currency: "PKR", weight_grams: null, attributes: null, is_default: false, is_active: false },
        { id: 2, sku: "B", name: "Large", price: 900, compare_at_price: null, currency: "PKR", weight_grams: null, attributes: null, is_default: false, is_active: true },
      ],
    });

    expect(defaultVariant(product)?.id).toBe(2);
  });
});

describe("primaryImage", () => {
  it("prefers the media item flagged is_primary", () => {
    const product = makeProduct({
      media: [
        { id: 1, type: "image", url: "a.jpg", alt_text: null, sort_order: 0, is_primary: false },
        { id: 2, type: "image", url: "b.jpg", alt_text: null, sort_order: 1, is_primary: true },
      ],
    });

    expect(primaryImage(product)?.id).toBe(2);
  });

  it("falls back to the lowest sort_order image", () => {
    const product = makeProduct({
      media: [
        { id: 1, type: "image", url: "a.jpg", alt_text: null, sort_order: 2, is_primary: false },
        { id: 2, type: "image", url: "b.jpg", alt_text: null, sort_order: 0, is_primary: false },
      ],
    });

    expect(primaryImage(product)?.id).toBe(2);
  });
});

describe("bestDiscount", () => {
  it("returns the variant with the largest markdown percentage", () => {
    const product = makeProduct({
      variants: [
        { id: 1, sku: "A", name: "Small", price: 900, compare_at_price: 1000, currency: "PKR", weight_grams: null, attributes: null, is_default: false, is_active: true },
        { id: 2, sku: "B", name: "Large", price: 600, compare_at_price: 1000, currency: "PKR", weight_grams: null, attributes: null, is_default: false, is_active: true },
      ],
    });

    const discount = bestDiscount(product);
    expect(discount?.variant.id).toBe(2);
    expect(discount?.percent).toBe(40);
  });

  it("returns null when no variant is marked down", () => {
    const product = makeProduct({
      variants: [
        { id: 1, sku: "A", name: "Small", price: 900, compare_at_price: null, currency: "PKR", weight_grams: null, attributes: null, is_default: false, is_active: true },
      ],
    });

    expect(bestDiscount(product)).toBeNull();
  });
});
