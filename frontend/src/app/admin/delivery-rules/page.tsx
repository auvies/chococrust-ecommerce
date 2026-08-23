"use client";

import { useState } from "react";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { DataTable, type Column } from "@/components/admin/ui/DataTable";
import { ConfirmButton } from "@/components/admin/ui/ConfirmButton";
import { Modal } from "@/components/admin/ui/Modal";
import { FormError } from "@/components/admin/ui/FormError";
import { SelectField, NumberField, TextField, CheckboxField } from "@/components/admin/ui/fields";
import { useAdminResource } from "@/lib/hooks/useAdminList";
import { getCategories } from "@/lib/api/catalog";
import {
  createDeliveryRule,
  deleteDeliveryRule,
  getDeliveryRules,
  updateDeliveryRule,
  type DeliveryRulePayload,
} from "@/lib/api/admin/deliveryRules";
import { ApiError } from "@/lib/api/client";
import { PERMISSIONS } from "@/lib/permissions";
import type { Category, DeliveryRule } from "@/types/api";

/**
 * Global + category-scoped delivery configuration in one place — the
 * Category Manager's own delivery-rules section (Phase 06) only ever
 * creates category-scoped rules for the category being viewed, so the
 * store-wide rules (the Khanewal local-delivery zone, the default
 * nationwide rule) had no admin surface at all until this page. Product-
 * scoped rules are still managed only via the Category Manager's parent
 * product context — out of scope here, same as before.
 */
export default function DeliveryRulesPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.categoriesManage]}>
      <DeliveryRulesManager />
    </RequirePermission>
  );
}

const emptyForm: DeliveryRulePayload = {
  scope: "global",
  category_id: null,
  delivery_type: "local",
  is_deliverable: true,
  min_order_amount: null,
  flat_fee: null,
  local_areas: null,
  estimated_minutes: null,
  priority: 0,
  is_active: true,
};

function DeliveryRulesManager() {
  const [creating, setCreating] = useState(false);
  const [editing, setEditing] = useState<DeliveryRule | null>(null);

  const { data, loading, refetch: load } = useAdminResource(
    async () => {
      const [rules, categories] = await Promise.all([
        getDeliveryRules({ sort: "priority", per_page: 100 }),
        getCategories({ per_page: 100 }),
      ]);
      return { rules: rules.data, categories: categories.data };
    },
    { rules: [] as DeliveryRule[], categories: [] as Category[] },
  );

  function categoryName(id: number | null): string {
    if (id === null) return "—";
    return data.categories.find((c) => c.id === id)?.name ?? `#${id}`;
  }

  const columns: Column<DeliveryRule>[] = [
    { key: "scope", header: "Scope", render: (r) => (r.scope === "category" ? `Category: ${categoryName(r.category_id)}` : r.scope) },
    { key: "delivery_type", header: "Delivery type", render: (r) => r.delivery_type },
    { key: "deliverable", header: "Deliverable", render: (r) => (r.is_deliverable ? "Yes" : "No") },
    { key: "fee", header: "Flat fee", render: (r) => r.flat_fee ?? "—" },
    { key: "areas", header: "Local areas", render: (r) => (r.local_areas && r.local_areas.length > 0 ? r.local_areas.join(", ") : "Any") },
    { key: "eta", header: "ETA (min)", render: (r) => r.estimated_minutes ?? "—" },
    { key: "active", header: "Active", render: (r) => (r.is_active ? "Yes" : "No") },
    {
      key: "actions",
      header: "",
      render: (r) => (
        <div className="flex gap-2">
          <button
            type="button"
            onClick={() => setEditing(r)}
            className="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300"
          >
            Edit
          </button>
          <ConfirmButton
            label="Delete"
            variant="danger"
            confirmMessage="Delete this delivery rule?"
            onConfirm={async () => {
              await deleteDeliveryRule(r.id);
              await load();
            }}
          />
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Delivery Rules"
        action={
          <button type="button" onClick={() => setCreating(true)} className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white dark:bg-amber-700">
            Add rule
          </button>
        }
      />
      <p className="mb-4 text-sm text-stone-500 dark:text-stone-400">
        Store-wide and category-scoped delivery eligibility, fees, local-delivery coverage area, and ETA — enforced server-side on every
        checkout (never hardcoded). Product-scoped rules are managed from that product&apos;s category in the Category Manager.
      </p>

      <DataTable columns={columns} rows={data.rules} rowKey={(r) => r.id} loading={loading} emptyMessage="No delivery rules configured yet." />

      {creating ? (
        <DeliveryRuleModal
          title="Add Delivery Rule"
          initial={emptyForm}
          categories={data.categories}
          onClose={() => setCreating(false)}
          onSubmit={async (payload) => {
            await createDeliveryRule(payload);
            setCreating(false);
            await load();
          }}
        />
      ) : null}

      {editing ? (
        <DeliveryRuleModal
          title="Edit Delivery Rule"
          initial={{
            scope: editing.scope,
            category_id: editing.category_id,
            product_id: editing.product_id,
            delivery_type: editing.delivery_type,
            is_deliverable: editing.is_deliverable,
            min_order_amount: editing.min_order_amount,
            flat_fee: editing.flat_fee,
            local_areas: editing.local_areas,
            estimated_minutes: editing.estimated_minutes,
            priority: editing.priority,
            is_active: editing.is_active,
          }}
          categories={data.categories}
          onClose={() => setEditing(null)}
          onSubmit={async (payload) => {
            await updateDeliveryRule(editing.id, payload);
            setEditing(null);
            await load();
          }}
        />
      ) : null}
    </div>
  );
}

function DeliveryRuleModal({
  title,
  initial,
  categories,
  onClose,
  onSubmit,
}: {
  title: string;
  initial: DeliveryRulePayload;
  categories: Category[];
  onClose: () => void;
  onSubmit: (payload: DeliveryRulePayload) => Promise<void>;
}) {
  const [form, setForm] = useState<DeliveryRulePayload>(initial);
  const [localAreasText, setLocalAreasText] = useState((initial.local_areas ?? []).join(", "));
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]> | undefined>();
  const [submitting, setSubmitting] = useState(false);

  function set<K extends keyof DeliveryRulePayload>(key: K, value: DeliveryRulePayload[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  async function handleSubmit() {
    setSubmitting(true);
    setError(null);
    try {
      const local_areas = localAreasText
        .split(",")
        .map((a) => a.trim())
        .filter(Boolean);
      await onSubmit({ ...form, local_areas: local_areas.length > 0 ? local_areas : null });
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
    <Modal title={title} onClose={onClose}>
      <FormError message={error} fieldErrors={fieldErrors} />
      <SelectField
        id="scope"
        label="Scope"
        value={form.scope}
        onChange={(scope) => set("scope", scope)}
        options={[
          { value: "global", label: "Global (store-wide)" },
          { value: "category", label: "Category" },
        ]}
      />
      {form.scope === "category" ? (
        <SelectField
          id="category_id"
          label="Category"
          value={String(form.category_id ?? "")}
          onChange={(value) => set("category_id", value ? Number(value) : null)}
          options={[{ value: "", label: "Select a category…" }, ...categories.map((c) => ({ value: String(c.id), label: c.name }))]}
        />
      ) : null}
      <SelectField
        id="delivery_type"
        label="Delivery type"
        value={form.delivery_type}
        onChange={(delivery_type) => set("delivery_type", delivery_type)}
        options={[
          { value: "both", label: "Both" },
          { value: "local", label: "Local only" },
          { value: "nationwide", label: "Nationwide only" },
        ]}
      />
      <CheckboxField id="is_deliverable" label="Deliverable" checked={form.is_deliverable ?? true} onChange={(v) => set("is_deliverable", v)} />
      <NumberField id="flat_fee" label="Flat fee" value={form.flat_fee ?? ""} onChange={(v) => set("flat_fee", v === "" ? null : v)} min={0} />
      <NumberField
        id="min_order_amount"
        label="Minimum order amount"
        value={form.min_order_amount ?? ""}
        onChange={(v) => set("min_order_amount", v === "" ? null : v)}
        min={0}
      />
      {form.delivery_type !== "nationwide" ? (
        <TextField
          id="local_areas"
          label="Local delivery areas (comma-separated cities/areas — leave blank for no restriction)"
          value={localAreasText}
          onChange={setLocalAreasText}
          placeholder="Khanewal, Kabirwala, Mian Channu"
        />
      ) : null}
      <NumberField
        id="estimated_minutes"
        label="Estimated delivery time (minutes)"
        value={form.estimated_minutes ?? ""}
        onChange={(v) => set("estimated_minutes", v === "" ? null : v)}
        min={1}
      />
      <NumberField id="priority" label="Priority" value={form.priority ?? 0} onChange={(v) => set("priority", v === "" ? 0 : v)} />
      <CheckboxField id="is_active" label="Active" checked={form.is_active ?? true} onChange={(v) => set("is_active", v)} />

      <div className="mt-4 flex justify-end gap-2">
        <button type="button" onClick={onClose} className="rounded-md border border-stone-300 px-4 py-2 text-sm dark:border-stone-700">
          Cancel
        </button>
        <button
          type="button"
          disabled={submitting}
          onClick={handleSubmit}
          className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700"
        >
          {submitting ? "Saving…" : "Save"}
        </button>
      </div>
    </Modal>
  );
}
