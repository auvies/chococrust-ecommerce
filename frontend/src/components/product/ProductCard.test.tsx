import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { ProductCard } from "./ProductCard";
import type { Product } from "@/types/api";

const product: Product = {
  id: 5,
  category_id: null,
  name: "Fudge Brownie",
  slug: "fudge-brownie",
  description: null,
  short_description: null,
  brand: "Choco Crust",
  status: "active",
  is_featured: true,
  variants: [
    {
      id: 1,
      sku: "BRW-1",
      name: "Regular",
      price: 450,
      compare_at_price: null,
      currency: "PKR",
      weight_grams: 200,
      attributes: null,
      is_default: true,
      is_active: true,
    },
  ],
  media: [],
  categories: [],
  created_at: "2026-01-01T00:00:00Z",
  updated_at: "2026-01-01T00:00:00Z",
};

describe("ProductCard", () => {
  it("links to the id+slug product URL and shows name and price", () => {
    render(<ProductCard product={product} />);

    const link = screen.getByRole("link");
    expect(link).toHaveAttribute("href", "/products/5/fudge-brownie");
    expect(screen.getByText("Fudge Brownie")).toBeInTheDocument();
    expect(screen.getByText(/450/)).toBeInTheDocument();
  });
});
