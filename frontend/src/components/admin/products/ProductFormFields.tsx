import { CheckboxField, SelectField, TextAreaField, TextField } from "@/components/admin/ui/fields";
import type { Category } from "@/types/api";

export interface ProductFormState {
  name: string;
  slug: string;
  category_id: string;
  description: string;
  short_description: string;
  brand: string;
  status: "draft" | "active" | "archived";
  is_featured: boolean;
}

export const emptyProductForm: ProductFormState = {
  name: "",
  slug: "",
  category_id: "",
  description: "",
  short_description: "",
  brand: "",
  status: "draft",
  is_featured: false,
};

export function ProductFormFields({
  value,
  onChange,
  categories,
}: {
  value: ProductFormState;
  onChange: (value: ProductFormState) => void;
  categories: Category[];
}) {
  return (
    <>
      <TextField id="name" label="Name" required value={value.name} onChange={(name) => onChange({ ...value, name })} />
      <TextField id="slug" label="Slug" required value={value.slug} onChange={(slug) => onChange({ ...value, slug })} />
      <SelectField
        id="category_id"
        label="Category"
        value={value.category_id}
        onChange={(category_id) => onChange({ ...value, category_id })}
        options={[{ value: "", label: "None" }, ...categories.map((c) => ({ value: String(c.id), label: c.name }))]}
      />
      <TextField id="brand" label="Brand" value={value.brand} onChange={(brand) => onChange({ ...value, brand })} />
      <TextField
        id="short_description"
        label="Short description"
        value={value.short_description}
        onChange={(short_description) => onChange({ ...value, short_description })}
      />
      <TextAreaField
        id="description"
        label="Description"
        value={value.description}
        onChange={(description) => onChange({ ...value, description })}
      />
      <SelectField
        id="status"
        label="Status"
        value={value.status}
        onChange={(status) => onChange({ ...value, status })}
        options={[
          { value: "draft", label: "Draft" },
          { value: "active", label: "Active" },
          { value: "archived", label: "Archived" },
        ]}
      />
      <CheckboxField
        id="is_featured"
        label="Featured on homepage"
        checked={value.is_featured}
        onChange={(is_featured) => onChange({ ...value, is_featured })}
      />
    </>
  );
}
