"use client";

import { useState } from "react";
import { TextField, CheckboxField } from "@/components/admin/ui/fields";
import { FormError } from "@/components/admin/ui/FormError";
import { ApiError } from "@/lib/api/client";
import type { AddressPayload } from "@/lib/api/addresses";
import type { Address } from "@/types/api";

const emptyForm: AddressPayload = {
  label: "",
  recipient_name: "",
  phone: "",
  line1: "",
  line2: "",
  city: "",
  area: "",
  is_default: false,
};

function addressToForm(address: Address): AddressPayload {
  return {
    label: address.label ?? "",
    recipient_name: address.recipient_name,
    phone: address.phone,
    line1: address.line1,
    line2: address.line2 ?? "",
    city: address.city,
    area: address.area ?? "",
    is_default: address.is_default,
  };
}

/** Shared between the checkout page's inline "add a new address" flow and the account address book. */
export function AddressForm({
  initial,
  onSubmit,
  onCancel,
  submitLabel = "Save address",
}: {
  initial?: Address;
  onSubmit: (payload: AddressPayload) => Promise<void>;
  onCancel?: () => void;
  submitLabel?: string;
}) {
  const [form, setForm] = useState<AddressPayload>(initial ? addressToForm(initial) : emptyForm);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]> | undefined>();
  const [submitting, setSubmitting] = useState(false);

  function set<K extends keyof AddressPayload>(key: K, value: AddressPayload[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  async function handleSubmit() {
    setSubmitting(true);
    setError(null);
    setFieldErrors(undefined);
    try {
      await onSubmit(form);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
        setFieldErrors(err.errors);
      } else {
        setError("Something went wrong.");
      }
      setSubmitting(false);
    }
  }

  return (
    <div className="flex flex-col gap-1">
      <FormError message={error} fieldErrors={fieldErrors} />
      <TextField id="label" label="Label (e.g. Home, Office)" value={form.label ?? ""} onChange={(v) => set("label", v)} />
      <TextField id="recipient_name" label="Recipient name" required value={form.recipient_name} onChange={(v) => set("recipient_name", v)} />
      <TextField id="phone" label="Phone" required value={form.phone} onChange={(v) => set("phone", v)} />
      <TextField id="line1" label="Address line 1" required value={form.line1} onChange={(v) => set("line1", v)} />
      <TextField id="line2" label="Address line 2" value={form.line2 ?? ""} onChange={(v) => set("line2", v)} />
      <TextField id="city" label="City" required value={form.city} onChange={(v) => set("city", v)} />
      <TextField id="area" label="Area / neighbourhood" value={form.area ?? ""} onChange={(v) => set("area", v)} />
      <CheckboxField id="is_default" label="Set as default address" checked={form.is_default ?? false} onChange={(v) => set("is_default", v)} />

      <div className="mt-2 flex justify-end gap-2">
        {onCancel ? (
          <button type="button" onClick={onCancel} className="rounded-md border border-stone-300 px-4 py-2 text-sm dark:border-stone-700">
            Cancel
          </button>
        ) : null}
        <button
          type="button"
          disabled={submitting}
          onClick={handleSubmit}
          className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700"
        >
          {submitting ? "Saving…" : submitLabel}
        </button>
      </div>
    </div>
  );
}
