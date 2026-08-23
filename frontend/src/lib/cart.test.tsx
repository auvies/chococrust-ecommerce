import { afterEach, beforeEach, describe, expect, it } from "vitest";
import { render, screen, fireEvent, waitFor } from "@testing-library/react";
import { CartProvider, useCart, type CartItem } from "./cart";

const sampleItem: Omit<CartItem, "quantity" | "customizationNote"> = {
  productVariantId: 1,
  productId: 10,
  productSlug: "chocolate-cake",
  productName: "Chocolate Cake",
  variantName: "1kg",
  sku: "CC-1KG",
  price: 1500,
  currency: "PKR",
  imageUrl: null,
};

const otherItem: Omit<CartItem, "quantity" | "customizationNote"> = {
  ...sampleItem,
  productVariantId: 2,
  variantName: "2kg",
  sku: "CC-2KG",
  price: 2800,
};

/** Exercises useCart() through real DOM interactions rather than calling the hook's functions directly, matching how the cart page/VariantSelector actually use it. */
function CartHarness() {
  const cart = useCart();

  return (
    <div>
      <button onClick={() => cart.addItem(sampleItem, 2, "Happy Birthday!")}>add-sample</button>
      <button onClick={() => cart.addItem(otherItem, 1)}>add-other</button>
      <button onClick={() => cart.updateQuantity(sampleItem.productVariantId, (cart.items[0]?.quantity ?? 0) + 1)}>increment-first</button>
      <button onClick={() => cart.updateQuantity(sampleItem.productVariantId, 0)}>zero-first</button>
      <button onClick={() => cart.removeItem(sampleItem.productVariantId)}>remove-first</button>
      <button onClick={() => cart.clear()}>clear</button>
      <p data-testid="count">{cart.itemCount}</p>
      <p data-testid="subtotal">{cart.subtotal}</p>
      <p data-testid="items">{cart.items.map((i) => `${i.productVariantId}:${i.quantity}:${i.customizationNote}`).join(",")}</p>
    </div>
  );
}

function renderCart() {
  return render(
    <CartProvider>
      <CartHarness />
    </CartProvider>,
  );
}

function click(label: string) {
  fireEvent.click(screen.getByText(label));
}

describe("cart", () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  afterEach(() => {
    window.localStorage.clear();
  });

  it("adds an item with quantity and a customization note", async () => {
    renderCart();

    click("add-sample");

    await waitFor(() => expect(screen.getByTestId("items")).toHaveTextContent("1:2:Happy Birthday!"));
    expect(screen.getByTestId("count")).toHaveTextContent("2");
    expect(screen.getByTestId("subtotal")).toHaveTextContent("3000");
  });

  it("merges a second add of the same variant into its existing line instead of duplicating it", async () => {
    renderCart();

    click("add-sample");
    click("add-sample");

    await waitFor(() => expect(screen.getByTestId("items")).toHaveTextContent("1:4:Happy Birthday!"));
  });

  it("tracks distinct variants as separate lines", async () => {
    renderCart();

    click("add-sample");
    click("add-other");

    await waitFor(() => expect(screen.getByTestId("count")).toHaveTextContent("3"));
    expect(screen.getByTestId("subtotal")).toHaveTextContent(String(2 * 1500 + 1 * 2800));
  });

  it("updateQuantity bumps an existing line's quantity (the cart page's +/- stepper)", async () => {
    renderCart();

    click("add-sample");
    await waitFor(() => expect(screen.getByTestId("items")).toHaveTextContent("1:2:"));

    click("increment-first");

    await waitFor(() => expect(screen.getByTestId("items")).toHaveTextContent("1:3:"));
    expect(screen.getByTestId("count")).toHaveTextContent("3");
  });

  it("updateQuantity to zero removes the line item", async () => {
    renderCart();

    click("add-sample");
    await waitFor(() => expect(screen.getByTestId("count")).toHaveTextContent("2"));

    click("zero-first");

    await waitFor(() => expect(screen.getByTestId("items")).toHaveTextContent(""));
    expect(screen.getByTestId("count")).toHaveTextContent("0");
  });

  it("removeItem drops the line without needing quantity zero", async () => {
    renderCart();

    click("add-sample");
    click("remove-first");

    await waitFor(() => expect(screen.getByTestId("items")).toHaveTextContent(""));
  });

  it("clear() empties the cart", async () => {
    renderCart();

    click("add-sample");
    click("add-other");
    click("clear");

    await waitFor(() => expect(screen.getByTestId("count")).toHaveTextContent("0"));
  });

  it("caps quantity at 100, mirroring StoreOrderRequest's items.*.quantity max", async () => {
    function OverflowHarness() {
      const cart = useCart();
      return (
        <div>
          <button onClick={() => cart.addItem(sampleItem, 150)}>add-over-limit</button>
          <button onClick={() => cart.updateQuantity(sampleItem.productVariantId, 999)}>set-over-limit</button>
          <p data-testid="qty">{cart.items[0]?.quantity ?? 0}</p>
        </div>
      );
    }
    render(
      <CartProvider>
        <OverflowHarness />
      </CartProvider>,
    );

    click("add-over-limit");
    await waitFor(() => expect(screen.getByTestId("qty")).toHaveTextContent("100"));

    click("set-over-limit");
    await waitFor(() => expect(screen.getByTestId("qty")).toHaveTextContent("100"));
  });

  it("persists to localStorage and rehydrates on the next mount", async () => {
    const { unmount } = renderCart();

    click("add-sample");
    await waitFor(() => expect(window.localStorage.getItem("cc_cart_v1")).toContain("chocolate-cake"));
    unmount();

    renderCart();
    await waitFor(() => expect(screen.getByTestId("items")).toHaveTextContent("1:2:Happy Birthday!"));
  });
});
