"use client";

import { useState } from "react";
import { useParams, useRouter } from "next/navigation";
import { useAdminResource } from "@/lib/hooks/useAdminList";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { FormError } from "@/components/admin/ui/FormError";
import { SelectField, NumberField, CheckboxField } from "@/components/admin/ui/fields";
import { DataTable, type Column } from "@/components/admin/ui/DataTable";
import { ConfirmButton } from "@/components/admin/ui/ConfirmButton";
import { Modal } from "@/components/admin/ui/Modal";
import {
  CategoryFormFields,
  categoryToForm,
  type CategoryFormState,
} from "@/components/admin/categories/CategoryFormFields";
import { SeoSection } from "@/components/admin/SeoSection";
import { getCategories, getCategory } from "@/lib/api/catalog";
import { updateCategory, uploadCategoryImage } from "@/lib/api/admin/categories";
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

export default function CategoryDetailPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.categoriesManage]}>
      <CategoryDetail />
    </RequirePermission>
  );
}

function CategoryDetail() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const router = useRouter();

  const { data, loading, refetch: load } = useAdminResource(
    async () => {
      const [cat, all] = await Promise.all([getCategory(id), getCategories({ per_page: 100 })]);
      return { category: cat, allCategories: all.data };
    },
    { category: null as Category | null, allCategories: [] as Category[] },
    id,
  );
  const { category, allCategories } = data;

  if (loading || !category) {
    return <p className="text-sm text-stone-500">Loading…</p>;
  }

  const subcategories = allCategories.filter((c) => c.parent_id === category.id);

  return (
    <div className="flex flex-col gap-8">
      <PageHeader title={`Category: ${category.name}`} />

      <CoreFieldsSection category={category} allCategories={allCategories} onSaved={load} />

      <ImageSection category={category} onSaved={load} />

      {subcategories.length > 0 ? (
        <section>
          <h2 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">Subcategories</h2>
          <ul className="list-disc pl-5 text-sm text-stone-700 dark:text-stone-300">
            {subcategories.map((sub) => (
              <li key={sub.id}>
                <button
                  type="button"
                  onClick={() => router.push(`/admin/categories/${sub.id}`)}
                  className="hover:underline"
                >
                  {sub.name}
                </button>
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      <SeoSection entityType="category" entityId={category.id} />

      <DeliveryRulesSection categoryId={category.id} />
    </div>
  );
}

function CoreFieldsSection({
  category,
  allCategories,
  onSaved,
}: {
  category: Category;
  allCategories: Category[];
  onSaved: () => Promise<void>;
}) {
  const [form, setForm] = useState<CategoryFormState>(() => categoryToForm(category));
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]> | undefined>();
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);

  async function handleSave() {
    setSaving(true);
    setError(null);
    setSaved(false);
    try {
      const updated = await updateCategory(category.id, {
        name: form.name,
        slug: form.slug,
        parent_id: form.parent_id ? Number(form.parent_id) : null,
        description: form.description || null,
        image_url: form.image_url || null,
        sort_order: form.sort_order === "" ? 0 : form.sort_order,
        is_active: form.is_active,
      });
      // Sync from the mutation's own response (the freshest copy) rather
      // than an effect watching the parent's separately re-fetched prop.
      setForm(categoryToForm(updated));
      await onSaved();
      setSaved(true);
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
        setFieldErrors(err.errors);
      } else {
        setError("Something went wrong.");
      }
    } finally {
      setSaving(false);
    }
  }

  return (
    <section className="max-w-xl rounded-lg border border-stone-200 p-4 dark:border-stone-800">
      <h2 className="mb-3 text-sm font-semibold text-stone-900 dark:text-stone-100">Details</h2>
      <FormError message={error} fieldErrors={fieldErrors} />
      <CategoryFormFields value={form} onChange={setForm} parentOptions={allCategories} excludeId={category.id} />
      <div className="flex items-center gap-3">
        <button
          type="button"
          onClick={handleSave}
          disabled={saving}
          className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700"
        >
          {saving ? "Saving…" : "Save changes"}
        </button>
        {saved ? <span className="text-sm text-green-700 dark:text-green-400">Saved.</span> : null}
      </div>
    </section>
  );
}

function ImageSection({ category, onSaved }: { category: Category; onSaved: () => Promise<void> }) {
  const [file, setFile] = useState<File | null>(null);
  const [altText, setAltText] = useState(category.alt_text ?? "");
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]> | undefined>();
  const [uploading, setUploading] = useState(false);

  async function handleUpload() {
    if (!file) return;
    setUploading(true);
    setError(null);
    try {
      await uploadCategoryImage(category.id, file, altText || undefined);
      setFile(null);
      await onSaved();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
        setFieldErrors(err.errors);
      } else {
        setError("Something went wrong.");
      }
    } finally {
      setUploading(false);
    }
  }

  return (
    <section className="max-w-xl rounded-lg border border-stone-200 p-4 dark:border-stone-800">
      <h2 className="mb-3 text-sm font-semibold text-stone-900 dark:text-stone-100">Image</h2>
      <FormError message={error} fieldErrors={fieldErrors} />
      {category.image_url ? (
        // eslint-disable-next-line @next/next/no-img-element -- admin preview of an arbitrary uploaded/external URL, not an optimizable local asset
        <img src={category.image_url} alt={category.alt_text ?? category.name} className="mb-3 h-32 w-32 rounded-md object-cover" />
      ) : (
        <p className="mb-3 text-sm text-stone-500">No image uploaded yet.</p>
      )}
      <div className="mb-3">
        <label htmlFor="category-image" className="mb-1 block text-sm font-medium text-stone-700 dark:text-stone-300">
          Replace image (JPEG/PNG/WebP, up to 5MB)
        </label>
        <input
          id="category-image"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          onChange={(e) => setFile(e.target.files?.[0] ?? null)}
          className="block w-full text-sm text-stone-700 dark:text-stone-300"
        />
      </div>
      <div className="mb-3">
        <label htmlFor="category-alt" className="mb-1 block text-sm font-medium text-stone-700 dark:text-stone-300">
          Alt text
        </label>
        <input
          id="category-alt"
          type="text"
          value={altText}
          onChange={(e) => setAltText(e.target.value)}
          className="w-full rounded-md border border-stone-300 px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-900 dark:text-stone-100"
        />
      </div>
      <button
        type="button"
        onClick={handleUpload}
        disabled={!file || uploading}
        className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700"
      >
        {uploading ? "Uploading…" : "Upload"}
      </button>
    </section>
  );
}

const emptyRuleForm: DeliveryRulePayload = {
  scope: "category",
  category_id: undefined,
  delivery_type: "both",
  is_deliverable: true,
  min_order_amount: null,
  flat_fee: null,
  priority: 0,
  is_active: true,
};

function DeliveryRulesSection({ categoryId }: { categoryId: number }) {
  const [creating, setCreating] = useState(false);
  const [editing, setEditing] = useState<DeliveryRule | null>(null);

  const { data: rules, loading, refetch: load } = useAdminResource(
    async () => (await getDeliveryRules({ filter: { category_id: categoryId } })).data,
    [] as DeliveryRule[],
    categoryId,
  );

  const columns: Column<DeliveryRule>[] = [
    { key: "delivery_type", header: "Delivery type", render: (r) => r.delivery_type },
    { key: "deliverable", header: "Deliverable", render: (r) => (r.is_deliverable ? "Yes" : "No") },
    { key: "fee", header: "Flat fee", render: (r) => r.flat_fee ?? "—" },
    { key: "min", header: "Min order", render: (r) => r.min_order_amount ?? "—" },
    { key: "priority", header: "Priority", render: (r) => r.priority },
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
    <section>
      <div className="mb-3 flex items-center justify-between">
        <h2 className="text-sm font-semibold text-stone-900 dark:text-stone-100">Delivery rules for this category</h2>
        <button
          type="button"
          onClick={() => setCreating(true)}
          className="rounded-md bg-stone-900 px-3 py-1.5 text-xs font-medium text-white dark:bg-amber-700"
        >
          Add rule
        </button>
      </div>

      <DataTable columns={columns} rows={rules} rowKey={(r) => r.id} loading={loading} emptyMessage="No delivery rules configured for this category yet — the global default applies." />

      {creating ? (
        <DeliveryRuleModal
          title="Add Delivery Rule"
          initial={{ ...emptyRuleForm, category_id: categoryId }}
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
            priority: editing.priority,
            is_active: editing.is_active,
          }}
          onClose={() => setEditing(null)}
          onSubmit={async (payload) => {
            await updateDeliveryRule(editing.id, payload);
            setEditing(null);
            await load();
          }}
        />
      ) : null}
    </section>
  );
}

function DeliveryRuleModal({
  title,
  initial,
  onClose,
  onSubmit,
}: {
  title: string;
  initial: DeliveryRulePayload;
  onClose: () => void;
  onSubmit: (payload: DeliveryRulePayload) => Promise<void>;
}) {
  const [form, setForm] = useState<DeliveryRulePayload>(initial);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]> | undefined>();
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit() {
    setSubmitting(true);
    setError(null);
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
    <Modal title={title} onClose={onClose}>
      <FormError message={error} fieldErrors={fieldErrors} />
      <SelectField
        id="delivery_type"
        label="Delivery type"
        value={form.delivery_type}
        onChange={(delivery_type) => setForm({ ...form, delivery_type })}
        options={[
          { value: "both", label: "Both" },
          { value: "local", label: "Local only" },
          { value: "nationwide", label: "Nationwide only" },
        ]}
      />
      <CheckboxField
        id="is_deliverable"
        label="Deliverable"
        checked={form.is_deliverable ?? true}
        onChange={(is_deliverable) => setForm({ ...form, is_deliverable })}
      />
      <NumberField
        id="flat_fee"
        label="Flat fee"
        value={form.flat_fee ?? ""}
        onChange={(flat_fee) => setForm({ ...form, flat_fee: flat_fee === "" ? null : flat_fee })}
        min={0}
      />
      <NumberField
        id="min_order_amount"
        label="Minimum order amount"
        value={form.min_order_amount ?? ""}
        onChange={(min_order_amount) => setForm({ ...form, min_order_amount: min_order_amount === "" ? null : min_order_amount })}
        min={0}
      />
      <NumberField
        id="priority"
        label="Priority"
        value={form.priority ?? 0}
        onChange={(priority) => setForm({ ...form, priority: priority === "" ? 0 : priority })}
      />
      <CheckboxField
        id="is_active"
        label="Active"
        checked={form.is_active ?? true}
        onChange={(is_active) => setForm({ ...form, is_active })}
      />
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
