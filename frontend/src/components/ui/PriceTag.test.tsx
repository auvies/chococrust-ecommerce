import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { PriceTag } from "./PriceTag";

describe("PriceTag", () => {
  it("shows only the price when there is no discount", () => {
    render(<PriceTag price={1000} currency="PKR" />);
    expect(screen.queryByText(/-\d+%/)).not.toBeInTheDocument();
  });

  it("shows a strikethrough compare-at price and a discount badge", () => {
    render(<PriceTag price={750} compareAtPrice={1000} currency="PKR" />);
    expect(screen.getByText("-25%")).toBeInTheDocument();
  });
});
