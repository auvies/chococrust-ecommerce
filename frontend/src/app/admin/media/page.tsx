"use client";

import { useRef, useState } from "react";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { DataTable, type Column } from "@/components/admin/ui/DataTable";
import { ConfirmButton } from "@/components/admin/ui/ConfirmButton";
import { FormError } from "@/components/admin/ui/FormError";
import { SelectField } from "@/components/admin/ui/fields";
import { useAdminResource } from "@/lib/hooks/useAdminList";
import { getProduct, getProducts } from "@/lib/api/catalog";
import { uploadMedia, deleteMedia } from "@/lib/api/admin/media";
import { ApiError } from "@/lib/api/client";
import { PERMISSIONS } from "@/lib/permissions";
import type { Product, ProductMedia } from "@/types/api";

/**
 * There is no global `GET /media` endpoint (media only exists as a
 * relation on a product), so this browses media one product at a time —
 * pick a product, see its images, upload/delete against it.
 */
export default function MediaPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.productsManage, PERMISSIONS.contentManage]}>
      <MediaManager />
    </RequirePermission>
  );
}

function MediaManager() {
  const [selectedId, setSelectedId] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [uploading, setUploading] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const { data: products } = useAdminResource(
    async () => (await getProducts({ per_page: 100, sort: "name" })).data,
    [] as Product[],
  );

  const { data: product, refetch: refetchProduct } = useAdminResource<Product | null>(
    () => (selectedId ? getProduct(Number(selectedId)) : Promise.resolve(null)),
    null,
    selectedId,
  );

  async function handleUpload() {
    const file = fileInputRef.current?.files?.[0];
    if (!file || !product) return;
    setUploading(true);
    setError(null);
    try {
      await uploadMedia({ file, productId: product.id, isPrimary: product.media.length === 0 });
      if (fileInputRef.current) fileInputRef.current.value = "";
      await refetchProduct();
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
      render: (m) => <img src={m.url} alt={m.alt_text ?? ""} className="h-14 w-14 rounded object-cover" />,
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
            await refetchProduct();
          }}
        />
      ),
    },
  ];

  return (
    <div>
      <PageHeader title="Media Manager" />

      <div className="mb-4 max-w-sm">
        <SelectField
          id="product"
          label="Product"
          value={selectedId}
          onChange={setSelectedId}
          options={[{ value: "", label: "Select a product…" }, ...products.map((p) => ({ value: String(p.id), label: p.name }))]}
        />
      </div>

      {product ? (
        <>
          <FormError message={error} />
          <div className="mb-3 flex items-center gap-2">
            <input ref={fileInputRef} type="file" accept="image/jpeg,image/png,image/webp" className="text-sm" />
            <button type="button" onClick={handleUpload} disabled={uploading} className="rounded-md bg-stone-900 px-3 py-1.5 text-xs font-medium text-white disabled:opacity-60 dark:bg-amber-700">
              {uploading ? "Uploading…" : "Upload"}
            </button>
          </div>
          <DataTable columns={columns} rows={product.media} rowKey={(m) => m.id} emptyMessage="No images uploaded for this product yet." />
        </>
      ) : (
        <p className="text-sm text-stone-500">Select a product to manage its media.</p>
      )}
    </div>
  );
}
