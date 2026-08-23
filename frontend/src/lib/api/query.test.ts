import { describe, expect, it } from "vitest";
import { emptyPage, toQueryString } from "./query";

describe("toQueryString", () => {
  it("builds filter[], sort, search, and pagination params", () => {
    const qs = toQueryString({
      filter: { category_id: 3, is_featured: true },
      sort: "-created_at",
      search: "cake",
      per_page: 12,
      page: 2,
    });

    expect(qs).toContain("filter%5Bcategory_id%5D=3");
    expect(qs).toContain("filter%5Bis_featured%5D=1");
    expect(qs).toContain("sort=-created_at");
    expect(qs).toContain("search=cake");
    expect(qs).toContain("per_page=12");
    expect(qs).toContain("page=2");
  });

  it("omits empty/undefined/null filter values", () => {
    const qs = toQueryString({ filter: { category_id: undefined, status: "" } });
    expect(qs).toBe("");
  });

  it("builds filter[col][]=... for an array value, one entry per item", () => {
    const qs = toQueryString({ filter: { status: ["pending", "cod_pending"] } });

    expect(qs).toContain("filter%5Bstatus%5D%5B%5D=pending");
    expect(qs).toContain("filter%5Bstatus%5D%5B%5D=cod_pending");
    expect(new URLSearchParams(qs.slice(1)).getAll("filter[status][]")).toEqual(["pending", "cod_pending"]);
  });

  it("returns an empty string for no params", () => {
    expect(toQueryString()).toBe("");
  });
});

describe("emptyPage", () => {
  it("returns a well-formed empty paginated envelope", () => {
    const page = emptyPage<number>(12);
    expect(page.data).toEqual([]);
    expect(page.meta.per_page).toBe(12);
    expect(page.meta.total).toBe(0);
  });
});
