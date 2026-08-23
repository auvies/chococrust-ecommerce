"use client";

import { useRef, useState } from "react";
import { useParams } from "next/navigation";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { FormError } from "@/components/admin/ui/FormError";
import { NumberField, TextField, CheckboxField } from "@/components/admin/ui/fields";
import { DataTable, type Column } from "@/components/admin/ui/DataTable";
import { ConfirmButton } from "@/components/admin/ui/ConfirmButton";
import { Modal } from "@/components/admin/ui/Modal";
import { SeoSection } from "@/components/admin/SeoSection";
import { ProductFormFields, type ProductFormState } from "@/components/admin/products/ProductFormFields";
import { useAdminResource } from "@/lib/hooks/useAdminList";
import { getCategories, getProduct } from "@/lib/api/catalog";
import { updateProduct, createVariant, updateVariant, deleteVariant, type VariantPayload } from "@/lib/api/admin/products";
import { uploadMedia, deleteMedia } from "@/lib/api/admin/media";
import { ApiError } from "@/lib/api/client";
import { PERMISSIONS } from "@/lib/permissions";
import type { Category, Product, ProductMedia, ProductVariant } from "@/types/api";

export default function ProductDetailPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.productsManage]}>
      <ProductDetail />
    </RequirePermission>
  );
}

function ProductDetail() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const { data, loading, refetch: load } = useAdminResource(
    async () => {
      const [p, cats] = await Promise.all([getProduct(id), getCategories({ per_page: 100 })]);
      return { product: p, categories: cats.data };
    },
    { product: null as Product | null, categories: [] as Category[] },
    id,
  );
  const { product, categories } = data;

  if (loading || !product) return <p className="text-sm text-stone-500">Loading…</p>;

  return (
    <div className="flex flex-col gap-8">
      <PageHeader title={`Product: ${product.name}`} />
      <CoreFieldsSection product={product} categories={categories} onSaved={load} />
      <VariantsSection product={product} onChanged={load} />
      <MediaSection product={product} onChanged={load} />
      <SeoSection entityType="product" entityId={product.id} />
    </div>
  );
}

function CoreFieldsSection({ product, categories, onSaved }: { product: Product; categories: Category[]; onSaved: () => Promise<void> }) {
  const [form, setForm] = useState<ProductFormState>({
    name: product.name,
    slug: product.slug,
    category_id: product.category_id ? String(product.category_id) : "",
    description: product.description ?? "",
    short_description: product.short_description ?? "",
    brand: product.brand ?? "",
    status: product.status as ProductFormState["status"],
    is_featured: product.is_featured,
  });
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]> | undefined>();
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);

  async function handleSave() {
    setSaving(true);
    setError(null);
    setSaved(false);
    try {
      await updateProduct(product.id, {
        name: form.name,
        slug: form.slug,
        category_id: form.category_id ? Number(form.category_id) : null,
        description: form.description || null,
        short_description: form.short_description || null,
        brand: form.brand || null,
        status: form.status,
        is_featured: form.is_featured,
      });
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
      <ProductFormFields value={form} onChange={setForm} categories={categories} />
      <div className="flex items-center gap-3">
        <button type="button" onClick={handleSave} disabled={saving} className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700">
          {saving ? "Saving…" : "Save changes"}
        </button>
        {saved ? <span className="text-sm text-green-700 dark:text-green-400">Saved.</span> : null}
      </div>
    </section>
  );
}

const emptyVariant: VariantPayload = { sku: "", name: "", price: 0, is_active: true };

function VariantsSection({ product, onChanged }: { product: Product; onChanged: () => Promise<void> }) {
  const [creating, setCreating] = useState(false);
  const [editing, setEditing] = useState<ProductVariant | null>(null);

  const columns: Column<ProductVariant>[] = [
    { key: "sku", header: "SKU", render: (v) => v.sku },
    { key: "name", header: "Name", render: (v) => v.name },
    { key: "price", header: "Price", render: (v) => v.price },
    { key: "compare", header: "Compare-at", render: (v) => v.compare_at_price ?? "—" },
    { key: "default", header: "Default", render: (v) => (v.is_default ? "Yes" : "No") },
    { key: "active", header: "Active", render: (v) => (v.is_active ? "Yes" : "No") },
    {
      key: "actions",
      header: "",
      render: (v) => (
        <div className="flex gap-2">
          <button type="button" onClick={() => setEditing(v)} className="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300">
            Edit
          </button>
          <ConfirmButton
            label="Delete"
            variant="danger"
            confirmMessage={`Delete variant "${v.name}"?`}
            onConfirm={async () => {
              await deleteVariant(product.id, v.id);
              await onChanged();
            }}
          />
        </div>
      ),
    },
  ];

  return (
    <section>
      <div className="mb-3 flex items-center justify-between">
        <h2 className="text-sm font-semibold text-stone-900 dark:text-stone-100">Variants</h2>
        <button type="button" onClick={() => setCreating(true)} className="rounded-md bg-stone-900 px-3 py-1.5 text-xs font-medium text-white dark:bg-amber-700">
          Add variant
        </button>
      </div>
      <DataTable columns={columns} rows={product.variants} rowKey={(v) => v.id} emptyMessage="No variants." />

      {creating ? (
        <VariantModal
          title="Add Variant"
          initial={emptyVariant}
          onClose={() => setCreating(false)}
          onSubmit={async (payload) => {
            await createVariant(product.id, payload);
            setCreating(false);
            await onChanged();
          }}
        />
      ) : null}

      {editing ? (
        <VariantModal
          title="Edit Variant"
          initial={{
            sku: editing.sku,
            name: editing.name,
            price: editing.price,
            compare_at_price: editing.compare_at_price,
            is_default: editing.is_default,
            is_active: editing.is_active,
          }}
          onClose={() => setEditing(null)}
          onSubmit={async (payload) => {
            await updateVariant(product.id, editing.id, payload);
            setEditing(null);
            await onChanged();
          }}
        />
      ) : null}
    </section>
  );
}

function VariantModal({
  title,
  initial,
  onClose,
  onSubmit,
}: {
  title: string;
  initial: VariantPayload;
  onClose: () => void;
  onSubmit: (payload: VariantPayload) => Promise<void>;
}) {
  const [form, setForm] = useState<VariantPayload>(initial);
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
      <TextField id="sku" label="SKU" required value={form.sku} onChange={(sku) => setForm({ ...form, sku })} />
      <TextField id="name" label="Name" required value={form.name} onChange={(name) => setForm({ ...form, name })} />
      <NumberField id="price" label="Price" required value={form.price} onChange={(price) => setForm({ ...form, price: price === "" ? 0 : price })} min={0} />
      <NumberField
        id="compare_at_price"
        label="Compare-at price"
        value={form.compare_at_price ?? ""}
        onChange={(compare_at_price) => setForm({ ...form, compare_at_price: compare_at_price === "" ? null : compare_at_price })}
        min={0}
      />
      <CheckboxField id="is_default" label="Default variant" checked={form.is_default ?? false} onChange={(is_default) => setForm({ ...form, is_default })} />
      <CheckboxField id="is_active" label="Active" checked={form.is_active ?? true} onChange={(is_active) => setForm({ ...form, is_active })} />
      <div className="mt-4 flex justify-end gap-2">
        <button type="button" onClick={onClose} className="rounded-md border border-stone-300 px-4 py-2 text-sm dark:border-stone-700">
          Cancel
        </button>
        <button type="button" disabled={submitting} onClick={handleSubmit} className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700">
          {submitting ? "Saving…" : "Save"}
        </button>
      </div>
    </Modal>
  );
}

function MediaSection({ product, onChanged }: { product: Product; onChanged: () => Promise<void> }) {
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  async function handleUpload() {
    const file = fileInputRef.current?.files?.[0];
    if (!file) return;
    setUploading(true);
    setError(null);
    try {
      await uploadMedia({ file, productId: product.id, isPrimary: product.media.length === 0 });
      if (fileInputRef.current) fileInputRef.current.value = "";
      await onChanged();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Upload failed.");
    } finally {
      setUploading(false);
    }
  }

  const columns: Column<ProductMedia>[] = [
    {
      key: "preview",
      header: "Image",
      // eslint-disable-next-line @next/next/no-img-element
      render: (m) => <img src={m.url} alt={m.alt_text ?? ""} className="h-12 w-12 rounded object-cover" />,
    },
    { key: "primary", header: "Primary", render: (m) => (m.is_primary ? "Yes" : "No") },
    {
      key: "actions",
      header: "",
      render: (m) => (
        <ConfirmButton
          label="Delete"
          variant="danger"
          confirmMessage="Delete this image?"
          onConfirm={async () => {
            await deleteMedia(m.id);
            await onChanged();
          }}
        />
      ),
    },
  ];

  return (
    <section>
      <h2 className="mb-3 text-sm font-semibold text-stone-900 dark:text-stone-100">Media</h2>
      <FormError message={error} />
      <div className="mb-3 flex items-center gap-2">
        <input ref={fileInputRef} type="file" accept="image/jpeg,image/png,image/webp" className="text-sm" />
        <button type="button" onClick={handleUpload} disabled={uploading} className="rounded-md bg-stone-900 px-3 py-1.5 text-xs font-medium text-white disabled:opacity-60 dark:bg-amber-700">
          {uploading ? "Uploading…" : "Upload"}
        </button>
      </div>
      <DataTable columns={columns} rows={product.media} rowKey={(m) => m.id} emptyMessage="No images uploaded yet." />
    </section>
  );
}
