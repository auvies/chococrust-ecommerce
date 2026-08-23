import { expect, test, vi } from "vitest";
import { render, screen } from "@testing-library/react";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn() }),
  usePathname: () => "/search",
  useSearchParams: () => new URLSearchParams(),
}));

const sampleProduct = {
  id: 1,
  category_id: 1,
  name: "Dark Chocolate Cake",
  slug: "dark-chocolate-cake",
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
};

const getCategories = vi.fn().mockResolvedValue({ data: [], links: {}, meta: {} });
const getProducts = vi.fn().mockResolvedValue({
  data: [sampleProduct],
  links: { first: null, last: null, prev: null, next: null },
  meta: { current_page: 1, from: 1, last_page: 1, path: "", per_page: 12, to: 1, total: 1 },
});

vi.mock("@/lib/api/catalog", () => ({
  getCategories: (...args: unknown[]) => getCategories(...args),
  getProducts: (...args: unknown[]) => getProducts(...args),
}));

test("with no query, browses all active products instead of prompting for a search term", async () => {
  const SearchPage = (await import("./page")).default;
  render(await SearchPage({ searchParams: Promise.resolve({}) }));

  expect(screen.getByRole("heading", { name: "All Products" })).toBeInTheDocument();
  expect(screen.getByText("Dark Chocolate Cake")).toBeInTheDocument();
  expect(getProducts).toHaveBeenCalledWith(
    expect.objectContaining({ filter: { status: "active", category_id: undefined } }),
  );
});

test("passes category and price filters through to the product query", async () => {
  const SearchPage = (await import("./page")).default;
  render(
    await SearchPage({
      searchParams: Promise.resolve({ category: "3", min_price: "500", max_price: "2000" }),
    }),
  );

  expect(getProducts).toHaveBeenCalledWith(
    expect.objectContaining({
      filter: { status: "active", category_id: 3 },
      min_price: 500,
      max_price: 2000,
    }),
  );
});

test("a search query is reflected in the heading and passed through as a search term", async () => {
  const SearchPage = (await import("./page")).default;
  render(await SearchPage({ searchParams: Promise.resolve({ q: "cake" }) }));

  expect(screen.getByRole("heading", { name: 'Search results for "cake"' })).toBeInTheDocument();
  expect(getProducts).toHaveBeenCalledWith(expect.objectContaining({ search: "cake" }));
});
