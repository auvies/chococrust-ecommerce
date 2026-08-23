"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { DataTable, type Column } from "@/components/admin/ui/DataTable";
import { ConfirmButton } from "@/components/admin/ui/ConfirmButton";
import { StatusBadge } from "@/components/admin/ui/StatusBadge";
import { Modal } from "@/components/admin/ui/Modal";
import { FormError } from "@/components/admin/ui/FormError";
import { TextField, NumberField } from "@/components/admin/ui/fields";
import { ProductFormFields, emptyProductForm, type ProductFormState } from "@/components/admin/products/ProductFormFields";
import { useAdminList } from "@/lib/hooks/useAdminList";
import { getCategories, getProducts } from "@/lib/api/catalog";
import { createProduct, deleteProduct } from "@/lib/api/admin/products";
import { ApiError } from "@/lib/api/client";
import { PERMISSIONS } from "@/lib/permissions";
import type { Category, Product } from "@/types/api";

export default function ProductsPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.productsView, PERMISSIONS.productsManage]}>
      <ProductsManager />
    </RequirePermission>
  );
}

function ProductsManager() {
  const [creating, setCreating] = useState(false);
  const [categories, setCategories] = useState<Category[]>([]);

  useEffect(() => {
    getCategories({ per_page: 100 }).then((r) => setCategories(r.data));
  }, []);

  const { data, loading, refetch } = useAdminList(() => getProducts({ sort: "name", per_page: 50 }));

  const columns: Column<Product>[] = [
    { key: "name", header: "Name", render: (p) => <Link href={`/admin/products/${p.id}`} className="font-medium hover:underline">{p.name}</Link> },
    { key: "category", header: "Category", render: (p) => categories.find((c) => c.id === p.category_id)?.name ?? "—" },
    { key: "status", header: "Status", render: (p) => <StatusBadge status={p.status} /> },
    { key: "featured", header: "Featured", render: (p) => (p.is_featured ? "Yes" : "No") },
    { key: "variants", header: "Variants", render: (p) => p.variants?.length ?? "—" },
    {
      key: "actions",
      header: "",
      render: (p) => (
        <div className="flex gap-2">
          <Link href={`/admin/products/${p.id}`} className="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300">
            Manage
          </Link>
          <ConfirmButton
            label="Delete"
            variant="danger"
            confirmMessage={`Delete "${p.name}"?`}
            onConfirm={async () => {
              await deleteProduct(p.id);
              await refetch();
            }}
          />
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Product Manager"
        action={
          <button
            type="button"
            onClick={() => setCreating(true)}
            className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white dark:bg-amber-700"
          >
            Add Product
          </button>
        }
      />

      <DataTable columns={columns} rows={data} rowKey={(p) => p.id} loading={loading} emptyMessage="No products yet." />

      {creating ? (
        <CreateProductModal categories={categories} onClose={() => setCreating(false)} onCreated={refetch} />
      ) : null}
    </div>
  );
}

function CreateProductModal({
  categories,
  onClose,
  onCreated,
}: {
  categories: Category[];
  onClose: () => void;
  onCreated: () => Promise<void>;
}) {
  const [form, setForm] = useState<ProductFormState>(emptyProductForm);
  const [sku, setSku] = useState("");
  const [price, setPrice] = useState<number | "">("");
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]> | undefined>();
  const [submitting, setSubmitting] = useState(false);
  const router = useRouter();

  async function handleSubmit() {
    setSubmitting(true);
    setError(null);
    try {
      const product = await createProduct({
        name: form.name,
        slug: form.slug,
        category_id: form.category_id ? Number(form.category_id) : null,
        description: form.description || null,
        short_description: form.short_description || null,
        brand: form.brand || null,
        status: form.status,
        is_featured: form.is_featured,
        variants: [{ sku, name: "Default", price: price === "" ? 0 : price, is_default: true }],
      });
      await onCreated();
      router.push(`/admin/products/${product.id}`);
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
    <Modal title="Add Product" onClose={onClose}>
      <FormError message={error} fieldErrors={fieldErrors} />
      <ProductFormFields value={form} onChange={setForm} categories={categories} />
      <p className="mb-2 text-xs font-medium uppercase tracking-wide text-stone-500">Starting variant (add more after creating)</p>
      <TextField id="sku" label="SKU" required value={sku} onChange={setSku} />
      <NumberField id="price" label="Price" required value={price} onChange={setPrice} min={0} />
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
          {submitting ? "Creating…" : "Create"}
        </button>
      </div>
    </Modal>
  );
}
