import { CheckboxField, NumberField, SelectField, TextAreaField, TextField } from "@/components/admin/ui/fields";
import type { Category } from "@/types/api";

export interface CategoryFormState {
  name: string;
  slug: string;
  parent_id: string;
  description: string;
  image_url: string;
  sort_order: number | "";
  is_active: boolean;
}

export const emptyCategoryForm: CategoryFormState = {
  name: "",
  slug: "",
  parent_id: "",
  description: "",
  image_url: "",
  sort_order: 0,
  is_active: true,
};

export function categoryToForm(category: Category): CategoryFormState {
  return {
    name: category.name,
    slug: category.slug,
    parent_id: category.parent_id ? String(category.parent_id) : "",
    description: category.description ?? "",
    image_url: category.image_url ?? "",
    sort_order: category.sort_order,
    is_active: category.is_active,
  };
}

export function CategoryFormFields({
  value,
  onChange,
  parentOptions,
  excludeId,
}: {
  value: CategoryFormState;
  onChange: (value: CategoryFormState) => void;
  parentOptions: Category[];
  excludeId?: number;
}) {
  return (
    <>
      <TextField id="name" label="Name" required value={value.name} onChange={(name) => onChange({ ...value, name })} />
      <TextField id="slug" label="Slug" required value={value.slug} onChange={(slug) => onChange({ ...value, slug })} />
      <SelectField
        id="parent_id"
        label="Parent category"
        value={value.parent_id}
        onChange={(parent_id) => onChange({ ...value, parent_id })}
        options={[
          { value: "", label: "None (top-level)" },
          ...parentOptions.filter((c) => c.id !== excludeId).map((c) => ({ value: String(c.id), label: c.name })),
        ]}
      />
      <TextAreaField
        id="description"
        label="Description"
        value={value.description}
        onChange={(description) => onChange({ ...value, description })}
      />
      <TextField
        id="image_url"
        label="Image URL"
        value={value.image_url}
        onChange={(image_url) => onChange({ ...value, image_url })}
      />
      <NumberField
        id="sort_order"
        label="Sort order"
        value={value.sort_order}
        onChange={(sort_order) => onChange({ ...value, sort_order })}
      />
      <CheckboxField
        id="is_active"
        label="Active (visible on the storefront)"
        checked={value.is_active}
        onChange={(is_active) => onChange({ ...value, is_active })}
      />
    </>
  );
}
