import { describe, expect, it } from "vitest";
import { discountPercent, formatPrice } from "./format";

describe("discountPercent", () => {
  it("computes a rounded percentage off", () => {
    expect(discountPercent(750, 1000)).toBe(25);
  });

  it("returns 0 when there is no markdown", () => {
    expect(discountPercent(1000, 1000)).toBe(0);
    expect(discountPercent(1200, 1000)).toBe(0);
  });
});

describe("formatPrice", () => {
  it("formats a PKR amount without decimals", () => {
    expect(formatPrice(1500, "PKR")).toContain("1,500");
  });
});
