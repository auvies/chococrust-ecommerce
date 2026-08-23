"use client";

import { useEffect, useState } from "react";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { SelectField } from "@/components/admin/ui/fields";
import { SeoSection } from "@/components/admin/SeoSection";
import { getCategories, getProducts } from "@/lib/api/catalog";
import { PERMISSIONS } from "@/lib/permissions";
import type { Category, Product } from "@/types/api";

export default function SeoPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.contentManage]}>
      <SeoManager />
    </RequirePermission>
  );
}

function SeoManager() {
  const [entityType, setEntityType] = useState<"category" | "product">("category");
  const [categories, setCategories] = useState<Category[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [entityId, setEntityId] = useState("");

  useEffect(() => {
    getCategories({ per_page: 100 }).then((r) => setCategories(r.data));
    getProducts({ per_page: 100, sort: "name" }).then((r) => setProducts(r.data));
  }, []);

  const options = entityType === "category" ? categories.map((c) => ({ value: String(c.id), label: c.name })) : products.map((p) => ({ value: String(p.id), label: p.name }));

  return (
    <div>
      <PageHeader title="SEO Manager" />

      <div className="mb-4 flex max-w-md gap-4">
        <SelectField
          id="entity_type"
          label="Type"
          value={entityType}
          onChange={(v) => {
            setEntityType(v as "category" | "product");
            setEntityId("");
          }}
          options={[{ value: "category", label: "Category" }, { value: "product", label: "Product" }]}
        />
        <SelectField id="entity_id" label={entityType === "category" ? "Category" : "Product"} value={entityId} onChange={setEntityId} options={[{ value: "", label: "Select…" }, ...options]} />
      </div>

      {entityId ? <SeoSection entityType={entityType} entityId={Number(entityId)} /> : <p className="text-sm text-stone-500">Select an item to edit its SEO metadata.</p>}
    </div>
  );
}
