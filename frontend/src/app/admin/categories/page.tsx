"use client";

import Link from "next/link";
import { useState } from "react";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { DataTable, type Column } from "@/components/admin/ui/DataTable";
import { ConfirmButton } from "@/components/admin/ui/ConfirmButton";
import { StatusBadge } from "@/components/admin/ui/StatusBadge";
import { Modal } from "@/components/admin/ui/Modal";
import { FormError } from "@/components/admin/ui/FormError";
import { CategoryFormFields, emptyCategoryForm, type CategoryFormState } from "@/components/admin/categories/CategoryFormFields";
import { useAdminList } from "@/lib/hooks/useAdminList";
import { getCategories } from "@/lib/api/catalog";
import { createCategory, deleteCategory } from "@/lib/api/admin/categories";
import { ApiError } from "@/lib/api/client";
import { PERMISSIONS } from "@/lib/permissions";
import type { Category } from "@/types/api";

export default function CategoriesPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.categoriesManage]}>
      <CategoriesManager />
    </RequirePermission>
  );
}

function CategoriesManager() {
  const [creating, setCreating] = useState(false);

  const { data, loading, refetch } = useAdminList(() => getCategories({ sort: "sort_order", per_page: 100 }));

  const columns: Column<Category>[] = [
    { key: "name", header: "Name", render: (c) => <Link href={`/admin/categories/${c.id}`} className="font-medium hover:underline">{c.name}</Link> },
    { key: "slug", header: "Slug", render: (c) => c.slug },
    { key: "parent", header: "Parent", render: (c) => data.find((p) => p.id === c.parent_id)?.name ?? "—" },
    { key: "sort_order", header: "Sort", render: (c) => c.sort_order },
    { key: "status", header: "Status", render: (c) => <StatusBadge status={c.is_active ? "active" : "inactive"} /> },
    {
      key: "actions",
      header: "",
      render: (c) => (
        <div className="flex gap-2">
          <Link href={`/admin/categories/${c.id}`} className="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300">
            Manage
          </Link>
          <ConfirmButton
            label="Archive"
            variant="danger"
            confirmMessage={`Archive "${c.name}"? This can be reversed by a database restore, not from this screen.`}
            onConfirm={async () => {
              await deleteCategory(c.id);
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
        title="Category Manager"
        action={
          <button
            type="button"
            onClick={() => setCreating(true)}
            className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white dark:bg-amber-700"
          >
            Add Category
          </button>
        }
      />

      <DataTable columns={columns} rows={data} rowKey={(c) => c.id} loading={loading} emptyMessage="No categories yet." />

      {creating ? (
        <CreateCategoryModal
          categories={data}
          onClose={() => setCreating(false)}
          onCreated={async () => {
            setCreating(false);
            await refetch();
          }}
        />
      ) : null}
    </div>
  );
}

function CreateCategoryModal({
  categories,
  onClose,
  onCreated,
}: {
  categories: Category[];
  onClose: () => void;
  onCreated: () => void;
}) {
  const [form, setForm] = useState<CategoryFormState>(emptyCategoryForm);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]> | undefined>();
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit() {
    setSubmitting(true);
    setError(null);
    try {
      await createCategory({
        name: form.name,
        slug: form.slug,
        parent_id: form.parent_id ? Number(form.parent_id) : null,
        description: form.description || null,
        image_url: form.image_url || null,
        sort_order: form.sort_order === "" ? 0 : form.sort_order,
        is_active: form.is_active,
      });
      onCreated();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
        setFieldErrors(err.errors);
      } else {
        setError("Something went wrong.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Modal title="Add Category" onClose={onClose}>
      <FormError message={error} fieldErrors={fieldErrors} />
      <CategoryFormFields value={form} onChange={setForm} parentOptions={categories} />
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
