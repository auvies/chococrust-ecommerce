"use client";

import { useEffect, useRef, useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { RequireCustomerAuth } from "@/components/account/RequireCustomerAuth";
import { AddressForm } from "@/components/account/AddressForm";
import { useAdminResource } from "@/lib/hooks/useAdminList";
import { useCart } from "@/lib/cart";
import { getMyAddresses, createAddress } from "@/lib/api/addresses";
import { createOrder, checkDeliveryEligibility, PAYMENT_METHODS, type DeliveryEligibility, type PaymentMethod } from "@/lib/api/orders";
import { ApiError } from "@/lib/api/client";
import { formatPrice } from "@/lib/format";
import { Container } from "@/components/ui/Container";
import type { Address } from "@/types/api";

export default function CheckoutPage() {
  return (
    <RequireCustomerAuth>
      <Checkout />
    </RequireCustomerAuth>
  );
}

function Checkout() {
  const { items, subtotal, clear } = useCart();
  const router = useRouter();
  const navigatingAway = useRef(false);

  const { data: addresses, loading: addressesLoading, refetch: refetchAddresses } = useAdminResource(getMyAddresses, [] as Address[]);
  const [addingAddress, setAddingAddress] = useState(false);
  const [selectedAddressId, setSelectedAddressId] = useState<number | null>(null);
  const [deliveryType, setDeliveryType] = useState<"local" | "nationwide">("local");
  const [contactName, setContactName] = useState("");
  const [contactPhone, setContactPhone] = useState("");
  const [contactEmail, setContactEmail] = useState("");
  const [notes, setNotes] = useState("");
  const [couponCode, setCouponCode] = useState("");
  const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>("cod");

  const [eligibility, setEligibility] = useState<DeliveryEligibility | null>(null);
  const [eligibilityError, setEligibilityError] = useState<string | null>(null);
  const [checkingEligibility, setCheckingEligibility] = useState(false);

  const [submitError, setSubmitError] = useState<string | null>(null);
  const [submitFieldErrors, setSubmitFieldErrors] = useState<Record<string, string[]> | undefined>();
  const [submitting, setSubmitting] = useState(false);

  // Stable across retries of the *same* checkout attempt so a double-click
  // or network retry replays instead of placing a second order (the
  // backend's `idempotent` middleware is what actually enforces this — see
  // routes/api/orders.php). Only replaced after a successful order.
  const idempotencyKeyRef = useRef<string>(crypto.randomUUID());

  useEffect(() => {
    if (!navigatingAway.current && items.length === 0) {
      router.replace("/cart");
    }
  }, [items, router]);

  useEffect(() => {
    if (selectedAddressId === null && addresses.length > 0) {
      const preferred = addresses.find((a) => a.is_default) ?? addresses[0];
      // Defaulting the form's selection once the address list arrives is a
      // one-time sync from fetched data, not a cascading-render loop the
      // rule is meant to catch — `selectedAddressId` guards it to fire once.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setSelectedAddressId(preferred.id);
      setContactName(preferred.recipient_name);
      setContactPhone(preferred.phone);
    }
  }, [addresses, selectedAddressId]);

  // Debounced live eligibility preview — re-checked whenever the address,
  // delivery type, or cart contents change, so an ineligible combination is
  // caught before submit (CLAUDE.md-style "don't allow checkout when
  // delivery eligibility fails" applied proactively, not just server-side).
  useEffect(() => {
    if (!selectedAddressId || items.length === 0) {
      // Clearing a stale eligibility result when its inputs disappear is a
      // one-time sync, not a cascading update loop.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setEligibility(null);
      return;
    }

    let cancelled = false;
    const timer = setTimeout(() => {
      setCheckingEligibility(true);
      setEligibilityError(null);
      checkDeliveryEligibility(
        items.map((i) => ({ product_variant_id: i.productVariantId, quantity: i.quantity })),
        deliveryType,
        selectedAddressId,
      )
        .then((result) => {
          if (!cancelled) setEligibility(result);
        })
        .catch((err) => {
          if (!cancelled) {
            setEligibility(null);
            setEligibilityError(err instanceof ApiError ? err.message : "Could not check delivery eligibility.");
          }
        })
        .finally(() => {
          if (!cancelled) setCheckingEligibility(false);
        });
    }, 350);

    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [selectedAddressId, deliveryType, items]);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    if (!selectedAddressId || !eligibility) return;

    setSubmitting(true);
    setSubmitError(null);
    setSubmitFieldErrors(undefined);

    try {
      const order = await createOrder(
        {
          items: items.map((i) => ({
            product_variant_id: i.productVariantId,
            quantity: i.quantity,
            customization_note: i.customizationNote || undefined,
          })),
          delivery_type: deliveryType,
          shipping_address_id: selectedAddressId,
          coupon_code: couponCode || undefined,
          contact_name: contactName,
          contact_phone: contactPhone,
          contact_email: contactEmail || undefined,
          notes: notes || undefined,
          payment_method: paymentMethod,
        },
        idempotencyKeyRef.current,
      );

      navigatingAway.current = true;
      clear();
      router.push(`/checkout/confirmation/${order.id}`);
    } catch (err) {
      if (err instanceof ApiError) {
        setSubmitError(err.message);
        setSubmitFieldErrors(err.errors);
      } else {
        setSubmitError("Something went wrong placing your order. Please try again.");
      }
      setSubmitting(false);
    }
  }

  if (items.length === 0) return null;

  const estimatedTotal = subtotal + (eligibility?.fee ?? 0);
  const canSubmit = Boolean(selectedAddressId) && Boolean(eligibility) && !checkingEligibility && !submitting;

  return (
    <main className="flex-1 py-6 sm:py-10">
      <Container className="flex max-w-3xl flex-col gap-6">
        <h1 className="text-2xl font-semibold tracking-tight text-stone-900 dark:text-stone-100 sm:text-3xl">Checkout</h1>

        <form onSubmit={handleSubmit} className="flex flex-col gap-6">
          <section className="rounded-lg border border-stone-200 p-4 dark:border-stone-800">
            <h2 className="mb-3 text-sm font-semibold text-stone-900 dark:text-stone-100">Delivery type</h2>
            <div className="flex flex-wrap gap-3">
              {(["local", "nationwide"] as const).map((type) => (
                <label
                  key={type}
                  className={`flex-1 cursor-pointer rounded-md border px-3 py-2 text-sm ${
                    deliveryType === type
                      ? "border-amber-700 bg-amber-50 dark:border-amber-600 dark:bg-amber-950/40"
                      : "border-stone-300 dark:border-stone-700"
                  }`}
                >
                  <input
                    type="radio"
                    name="delivery_type"
                    value={type}
                    checked={deliveryType === type}
                    onChange={() => setDeliveryType(type)}
                    className="sr-only"
                  />
                  {type === "local" ? "Local delivery (Khanewal area, ~2 hours)" : "Nationwide shipping"}
                </label>
              ))}
            </div>
          </section>

          <section className="rounded-lg border border-stone-200 p-4 dark:border-stone-800">
            <div className="mb-3 flex items-center justify-between">
              <h2 className="text-sm font-semibold text-stone-900 dark:text-stone-100">Delivery address</h2>
              {!addingAddress ? (
                <button type="button" onClick={() => setAddingAddress(true)} className="text-xs font-medium text-amber-800 hover:underline dark:text-amber-500">
                  + Add new address
                </button>
              ) : null}
            </div>

            {addingAddress ? (
              <AddressForm
                submitLabel="Save and use this address"
                onCancel={() => setAddingAddress(false)}
                onSubmit={async (payload) => {
                  const created = await createAddress(payload);
                  await refetchAddresses();
                  setSelectedAddressId(created.id);
                  setContactName(created.recipient_name);
                  setContactPhone(created.phone);
                  setAddingAddress(false);
                }}
              />
            ) : addressesLoading ? (
              <p className="text-sm text-stone-500">Loading addresses…</p>
            ) : addresses.length === 0 ? (
              <p className="text-sm text-stone-500">You have no saved addresses yet — add one above.</p>
            ) : (
              <div className="flex flex-col gap-2">
                {addresses.map((address) => (
                  <label
                    key={address.id}
                    className={`cursor-pointer rounded-md border px-3 py-2 text-sm ${
                      selectedAddressId === address.id
                        ? "border-amber-700 bg-amber-50 dark:border-amber-600 dark:bg-amber-950/40"
                        : "border-stone-300 dark:border-stone-700"
                    }`}
                  >
                    <input
                      type="radio"
                      name="shipping_address_id"
                      className="sr-only"
                      checked={selectedAddressId === address.id}
                      onChange={() => {
                        setSelectedAddressId(address.id);
                        setContactName(address.recipient_name);
                        setContactPhone(address.phone);
                      }}
                    />
                    <span className="font-medium text-stone-900 dark:text-stone-100">
                      {address.label ? `${address.label} — ` : ""}
                      {address.recipient_name}
                    </span>
                    <br />
                    {address.line1}
                    {address.line2 ? `, ${address.line2}` : ""}, {[address.area, address.city].filter(Boolean).join(", ")}
                  </label>
                ))}
              </div>
            )}
          </section>

          <section className="rounded-lg border border-stone-200 p-4 dark:border-stone-800" aria-live="polite">
            <h2 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">Delivery eligibility</h2>
            {checkingEligibility ? (
              <p className="text-sm text-stone-500">Checking delivery eligibility…</p>
            ) : eligibilityError ? (
              <p className="text-sm text-red-600 dark:text-red-400">{eligibilityError}</p>
            ) : eligibility ? (
              <p className="text-sm text-green-700 dark:text-green-400">
                Deliverable — fee {formatPrice(eligibility.fee)}
                {eligibility.estimated_minutes ? `, estimated within ${Math.round(eligibility.estimated_minutes / 60)} hour(s)` : ""}.
              </p>
            ) : (
              <p className="text-sm text-stone-500">Select a delivery address to check eligibility.</p>
            )}
          </section>

          <section className="rounded-lg border border-stone-200 p-4 dark:border-stone-800">
            <h2 className="mb-3 text-sm font-semibold text-stone-900 dark:text-stone-100">Contact details</h2>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <label className="flex flex-col gap-1 text-sm text-stone-700 dark:text-stone-300">
                Name
                <input required value={contactName} onChange={(e) => setContactName(e.target.value)} className="rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-900" />
              </label>
              <label className="flex flex-col gap-1 text-sm text-stone-700 dark:text-stone-300">
                Phone
                <input required value={contactPhone} onChange={(e) => setContactPhone(e.target.value)} className="rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-900" />
              </label>
              <label className="flex flex-col gap-1 text-sm text-stone-700 dark:text-stone-300 sm:col-span-2">
                Email (optional)
                <input type="email" value={contactEmail} onChange={(e) => setContactEmail(e.target.value)} className="rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-900" />
              </label>
              <label className="flex flex-col gap-1 text-sm text-stone-700 dark:text-stone-300 sm:col-span-2">
                Order notes (optional)
                <textarea rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} className="rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-900" />
              </label>
              <label className="flex flex-col gap-1 text-sm text-stone-700 dark:text-stone-300 sm:col-span-2">
                Coupon code (optional)
                <input value={couponCode} onChange={(e) => setCouponCode(e.target.value.toUpperCase())} className="rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-900" />
              </label>
            </div>
          </section>

          <section className="rounded-lg border border-stone-200 p-4 dark:border-stone-800">
            <h2 className="mb-3 text-sm font-semibold text-stone-900 dark:text-stone-100">Payment method</h2>
            <div className="flex flex-col gap-2">
              {PAYMENT_METHODS.map((method) => (
                <label
                  key={method.value}
                  className={`flex items-center justify-between rounded-md border px-3 py-2 text-sm ${
                    !method.live
                      ? "cursor-not-allowed border-stone-200 opacity-60 dark:border-stone-800"
                      : paymentMethod === method.value
                        ? "cursor-pointer border-amber-700 bg-amber-50 dark:border-amber-600 dark:bg-amber-950/40"
                        : "cursor-pointer border-stone-300 dark:border-stone-700"
                  }`}
                >
                  <span className="flex items-center gap-2">
                    <input
                      type="radio"
                      name="payment_method"
                      value={method.value}
                      checked={paymentMethod === method.value}
                      disabled={!method.live}
                      onChange={() => setPaymentMethod(method.value)}
                      className="sr-only"
                    />
                    {method.label}
                  </span>
                  {/* Not yet integrated with a live gateway — no OTP/redirect
                      flow exists, so it's shown but not selectable rather
                      than pretending to accept a payment it can't collect. */}
                  {!method.live ? (
                    <span className="text-xs text-stone-500 dark:text-stone-500">Coming soon</span>
                  ) : null}
                </label>
              ))}
            </div>
          </section>

          <section className="rounded-lg border border-stone-200 p-4 dark:border-stone-800">
            <h2 className="mb-3 text-sm font-semibold text-stone-900 dark:text-stone-100">Order summary</h2>
            <ul className="mb-3 flex flex-col gap-1 text-sm text-stone-600 dark:text-stone-400">
              {items.map((item) => (
                <li key={item.productVariantId} className="flex justify-between gap-2">
                  <span>
                    {item.productName} ({item.variantName}) × {item.quantity}
                  </span>
                  <span>{formatPrice(item.price * item.quantity, item.currency)}</span>
                </li>
              ))}
            </ul>
            <div className="flex justify-between border-t border-stone-200 pt-2 text-sm dark:border-stone-800">
              <span>Subtotal</span>
              <span>{formatPrice(subtotal)}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span>Delivery fee</span>
              <span>{eligibility ? formatPrice(eligibility.fee) : "—"}</span>
            </div>
            <div className="mt-2 flex justify-between border-t border-stone-200 pt-2 font-semibold text-stone-900 dark:border-stone-800 dark:text-stone-100">
              <span>Estimated total</span>
              <span>{formatPrice(estimatedTotal)}</span>
            </div>
            <p className="mt-1 text-xs text-stone-500 dark:text-stone-500">
              Coupon discounts are applied when your order is placed. Payment:{" "}
              {PAYMENT_METHODS.find((m) => m.value === paymentMethod)?.label}.
            </p>
          </section>

          {submitError ? (
            <div className="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-400">
              <p>{submitError}</p>
              {submitFieldErrors ? (
                <ul className="mt-1 list-disc pl-5">
                  {Object.entries(submitFieldErrors).map(([field, messages]) => (
                    <li key={field}>{messages.join(" ")}</li>
                  ))}
                </ul>
              ) : null}
            </div>
          ) : null}

          <button
            type="submit"
            disabled={!canSubmit}
            className="rounded-md bg-stone-900 px-4 py-3 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700"
          >
            {submitting ? "Placing order…" : "Place order"}
          </button>
        </form>
      </Container>
    </main>
  );
}
