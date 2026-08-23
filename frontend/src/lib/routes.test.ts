import { describe, expect, it } from "vitest";
import { categoryHref, productHref } from "./routes";

describe("routes", () => {
  it("builds an id+slug product URL", () => {
    expect(productHref({ id: 42, slug: "chocolate-cake" })).toBe("/products/42/chocolate-cake");
  });

  it("builds an id+slug category URL", () => {
    expect(categoryHref({ id: 7, slug: "cakes" })).toBe("/categories/7/cakes");
  });
});
