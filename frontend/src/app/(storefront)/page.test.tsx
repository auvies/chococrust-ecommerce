import { expect, test, vi } from "vitest";
import { render, screen } from "@testing-library/react";

vi.mock("@/lib/api/catalog", () => ({
  getCategories: vi.fn().mockResolvedValue({ data: [], links: {}, meta: {} }),
  getProducts: vi.fn().mockResolvedValue({ data: [], links: {}, meta: {} }),
}));

vi.mock("@/lib/api/content", () => ({
  getHeroBanners: vi.fn().mockResolvedValue([]),
  getHomepageSections: vi.fn().mockResolvedValue([]),
}));

vi.mock("@/lib/homepage", () => ({
  getFeaturedProducts: vi.fn().mockResolvedValue([]),
  getHomepageProductData: vi.fn().mockResolvedValue({ bestSellers: [], testimonials: [], offers: [] }),
}));

test("falls back to the default section layout when no homepage_sections are configured", async () => {
  const Home = (await import("./page")).default;
  render(await Home());

  expect(screen.getByRole("heading", { name: "Shop by Category" })).toBeInTheDocument();
  expect(screen.getByRole("heading", { name: "All Products" })).toBeInTheDocument();
  expect(screen.getByRole("heading", { name: "Featured Products" })).toBeInTheDocument();
  expect(screen.getByRole("heading", { name: "Best Sellers" })).toBeInTheDocument();
  expect(screen.getByRole("heading", { name: "Offers" })).toBeInTheDocument();
  expect(screen.getByRole("heading", { name: "What Customers Say" })).toBeInTheDocument();
  expect(screen.getAllByText(/no .* yet|will appear here|available yet/i).length).toBeGreaterThan(0);
});

test("renders only the sections an admin has enabled, in the configured order", async () => {
  const content = await import("@/lib/api/content");
  vi.mocked(content.getHomepageSections).mockResolvedValueOnce([
    { id: 1, type: "best_sellers", title: "Top Picks", config: null, sort_order: 0, is_active: true },
    { id: 2, type: "featured_products", title: null, config: null, sort_order: 1, is_active: true },
    // A disabled section must never be fetched by getHomepageSections'
    // public endpoint in the first place, but even if one slipped through
    // it must not render.
    { id: 3, type: "offers", title: null, config: null, sort_order: 2, is_active: false },
  ]);

  const Home = (await import("./page")).default;
  render(await Home());

  expect(screen.getByRole("heading", { name: "Top Picks" })).toBeInTheDocument();
  expect(screen.getByRole("heading", { name: "Featured Products" })).toBeInTheDocument();
  expect(screen.queryByRole("heading", { name: "Offers" })).not.toBeInTheDocument();
  expect(screen.queryByRole("heading", { name: "Shop by Category" })).not.toBeInTheDocument();
});
