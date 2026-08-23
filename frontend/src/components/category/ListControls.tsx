"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useState, type FormEvent } from "react";
import type { Category } from "@/types/api";

const SORT_OPTIONS = [
  { value: "name", label: "Name (A–Z)" },
  { value: "-name", label: "Name (Z–A)" },
  { value: "-created_at", label: "Newest" },
  { value: "created_at", label: "Oldest" },
];

function flattenCategories(categories: Category[], depth = 0): { id: number; label: string }[] {
  return categories.flatMap((category) => [
    { id: category.id, label: `${"— ".repeat(depth)}${category.name}` },
    ...flattenCategories(category.children ?? [], depth + 1),
  ]);
}

/**
 * Drives `?search=&sort=&category=&min_price=&max_price=` on the current
 * route via a normal navigation — no client-side state duplication of
 * server data. The `categories`/price props are optional: pages that don't
 * need catalog-wide filtering (e.g. a single category's own product list)
 * just get the search+sort controls, unchanged from before.
 */
export function ListControls({
  initialSearch = "",
  categories,
  initialCategory = "",
  initialMinPrice = "",
  initialMaxPrice = "",
}: {
  initialSearch?: string;
  categories?: Category[];
  initialCategory?: string;
  initialMinPrice?: string;
  initialMaxPrice?: string;
}) {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const [search, setSearch] = useState(initialSearch);
  const [minPrice, setMinPrice] = useState(initialMinPrice);
  const [maxPrice, setMaxPrice] = useState(initialMaxPrice);

  function updateParam(key: string, value: string) {
    const params = new URLSearchParams(searchParams.toString());
    if (value) params.set(key, value);
    else params.delete(key);
    params.delete("page");
    router.push(`${pathname}?${params.toString()}`);
  }

  function handleFilterSubmit(event: FormEvent) {
    event.preventDefault();
    const params = new URLSearchParams(searchParams.toString());
    const set = (key: string, value: string) => (value ? params.set(key, value) : params.delete(key));
    set("q", search.trim());
    set("min_price", minPrice.trim());
    set("max_price", maxPrice.trim());
    params.delete("page");
    router.push(`${pathname}?${params.toString()}`);
  }

  const flatCategories = categories ? flattenCategories(categories) : [];

  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <form onSubmit={handleFilterSubmit} className="flex flex-wrap items-center gap-2">
        <label htmlFor="list-search" className="sr-only">
          Search within results
        </label>
        <input
          id="list-search"
          type="search"
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          placeholder="Filter results..."
          className="w-full min-w-0 flex-1 rounded-full border border-stone-300 bg-white px-4 py-2 text-sm sm:w-auto dark:border-stone-700 dark:bg-stone-900"
        />

        {categories ? (
          <>
            <label htmlFor="list-category" className="sr-only">
              Category
            </label>
            <select
              id="list-category"
              defaultValue={initialCategory}
              onChange={(event) => updateParam("category", event.target.value)}
              className="rounded-md border border-stone-300 bg-white px-2 py-1.5 text-sm dark:border-stone-700 dark:bg-stone-900"
            >
              <option value="">All categories</option>
              {flatCategories.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.label}
                </option>
              ))}
            </select>

            <label htmlFor="list-min-price" className="sr-only">
              Minimum price
            </label>
            <input
              id="list-min-price"
              type="number"
              min="0"
              inputMode="decimal"
              value={minPrice}
              onChange={(event) => setMinPrice(event.target.value)}
              placeholder="Min PKR"
              className="w-24 rounded-md border border-stone-300 bg-white px-2 py-1.5 text-sm dark:border-stone-700 dark:bg-stone-900"
            />

            <label htmlFor="list-max-price" className="sr-only">
              Maximum price
            </label>
            <input
              id="list-max-price"
              type="number"
              min="0"
              inputMode="decimal"
              value={maxPrice}
              onChange={(event) => setMaxPrice(event.target.value)}
              placeholder="Max PKR"
              className="w-24 rounded-md border border-stone-300 bg-white px-2 py-1.5 text-sm dark:border-stone-700 dark:bg-stone-900"
            />
          </>
        ) : null}

        <button
          type="submit"
          className="shrink-0 rounded-full border border-stone-300 px-4 py-2 text-sm font-medium dark:border-stone-700"
        >
          Filter
        </button>
      </form>

      <label className="flex items-center gap-2 text-sm">
        Sort by
        <select
          defaultValue={searchParams.get("sort") ?? "name"}
          onChange={(event) => updateParam("sort", event.target.value)}
          className="rounded-md border border-stone-300 bg-white px-2 py-1.5 text-sm dark:border-stone-700 dark:bg-stone-900"
        >
          {SORT_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      </label>
    </div>
  );
}
