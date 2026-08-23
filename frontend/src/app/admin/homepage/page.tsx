"use client";

import { useState } from "react";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { DataTable, type Column } from "@/components/admin/ui/DataTable";
import { ConfirmButton } from "@/components/admin/ui/ConfirmButton";
import { Modal } from "@/components/admin/ui/Modal";
import { FormError } from "@/components/admin/ui/FormError";
import { CheckboxField, NumberField, TextAreaField, TextField } from "@/components/admin/ui/fields";
import { useAdminResource } from "@/lib/hooks/useAdminList";
import { getHomepageSections } from "@/lib/api/content";
import { createHomepageSection, updateHomepageSection, deleteHomepageSection, type HomepageSectionPayload } from "@/lib/api/admin/content";
import { ApiError } from "@/lib/api/client";
import { PERMISSIONS } from "@/lib/permissions";
import type { HomepageSection } from "@/types/api";

export default function HomepagePage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.contentManage]}>
      <HomepageManager />
    </RequirePermission>
  );
}

const emptyForm: HomepageSectionPayload = { type: "", title: "", config: {}, sort_order: 0, is_active: true };

function HomepageManager() {
  const [creating, setCreating] = useState(false);
  const [editing, setEditing] = useState<HomepageSection | null>(null);

  const { data: sections, loading, refetch: load } = useAdminResource(getHomepageSections, [] as HomepageSection[]);

  const columns: Column<HomepageSection>[] = [
    { key: "type", header: "Type", render: (s) => s.type },
    { key: "title", header: "Title", render: (s) => s.title ?? "—" },
    { key: "sort", header: "Sort", render: (s) => s.sort_order },
    { key: "active", header: "Active", render: (s) => (s.is_active ? "Yes" : "No") },
    {
      key: "actions",
      header: "",
      render: (s) => (
        <div className="flex gap-2">
          <button type="button" onClick={() => setEditing(s)} className="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300">
            Edit
          </button>
          <ConfirmButton
            label="Delete"
            variant="danger"
            confirmMessage={`Delete section "${s.type}"?`}
            onConfirm={async () => {
              await deleteHomepageSection(s.id);
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
        title="Homepage Manager"
        action={
          <button type="button" onClick={() => setCreating(true)} className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white dark:bg-amber-700">
            Add Section
          </button>
        }
      />
      <DataTable columns={columns} rows={sections} rowKey={(s) => s.id} loading={loading} emptyMessage="No homepage sections configured yet." />

      {creating ? (
        <SectionModal title="Add Section" initial={emptyForm} onClose={() => setCreating(false)} onSubmit={async (p) => { await createHomepageSection(p); setCreating(false); await load(); }} />
      ) : null}
      {editing ? (
        <SectionModal
          title="Edit Section"
          initial={{ type: editing.type, title: editing.title ?? "", config: editing.config ?? {}, sort_order: editing.sort_order, is_active: editing.is_active }}
          onClose={() => setEditing(null)}
          onSubmit={async (p) => { await updateHomepageSection(editing.id, p); setEditing(null); await load(); }}
        />
      ) : null}
    </div>
  );
}

function SectionModal({ title, initial, onClose, onSubmit }: { title: string; initial: HomepageSectionPayload; onClose: () => void; onSubmit: (p: HomepageSectionPayload) => Promise<void> }) {
  const [type, setType] = useState(initial.type);
  const [sectionTitle, setSectionTitle] = useState(initial.title ?? "");
  const [configText, setConfigText] = useState(JSON.stringify(initial.config ?? {}, null, 2));
  const [sortOrder, setSortOrder] = useState<number | "">(initial.sort_order ?? 0);
  const [isActive, setIsActive] = useState(initial.is_active ?? true);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]> | undefined>();
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit() {
    setSubmitting(true);
    setError(null);
    try {
      const config = JSON.parse(configText || "{}");
      await onSubmit({ type, title: sectionTitle || null, config, sort_order: sortOrder === "" ? 0 : sortOrder, is_active: isActive });
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
        setFieldErrors(err.errors);
      } else if (err instanceof SyntaxError) {
        setError("Config must be valid JSON.");
      } else {
        setError("Something went wrong.");
      }
      setSubmitting(false);
    }
  }

  return (
    <Modal title={title} onClose={onClose}>
      <FormError message={error} fieldErrors={fieldErrors} />
      <TextField id="type" label="Type (e.g. featured_products, best_sellers)" required value={type} onChange={setType} />
      <TextField id="title" label="Title" value={sectionTitle} onChange={setSectionTitle} />
      <TextAreaField id="config" label="Config (JSON)" value={configText} onChange={setConfigText} rows={5} />
      <NumberField id="sort_order" label="Sort order" value={sortOrder} onChange={setSortOrder} />
      <CheckboxField id="is_active" label="Active" checked={isActive} onChange={setIsActive} />
      <div className="mt-4 flex justify-end gap-2">
        <button type="button" onClick={onClose} className="rounded-md border border-stone-300 px-4 py-2 text-sm dark:border-stone-700">Cancel</button>
        <button type="button" disabled={submitting} onClick={handleSubmit} className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700">
          {submitting ? "Saving…" : "Save"}
        </button>
      </div>
    </Modal>
  );
}
