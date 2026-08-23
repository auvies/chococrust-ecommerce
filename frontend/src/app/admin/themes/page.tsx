"use client";

import { useState } from "react";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { DataTable, type Column } from "@/components/admin/ui/DataTable";
import { ConfirmButton } from "@/components/admin/ui/ConfirmButton";
import { Modal } from "@/components/admin/ui/Modal";
import { FormError } from "@/components/admin/ui/FormError";
import { TextField } from "@/components/admin/ui/fields";
import { useAdminResource } from "@/lib/hooks/useAdminList";
import { getThemes, createTheme, updateTheme, activateTheme, deactivateTheme } from "@/lib/api/admin/content";
import { ApiError } from "@/lib/api/client";
import { PERMISSIONS } from "@/lib/permissions";
import type { Theme, ThemeConfig } from "@/types/api";

export default function ThemesPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.contentManage]}>
      <ThemesManager />
    </RequirePermission>
  );
}

const DEFAULT_CONFIG: ThemeConfig = {
  background: "#FFFBF5",
  surface: "#F5EDE4",
  text_color: "#2B1B12",
  primary_color: "#5B2E1E",
  secondary_color: "#D97706",
  typography: { font_family: "'Nunito', sans-serif", heading_font_family: "'Playfair Display', serif" },
  font_sizes: { sm: "14px", base: "16px", lg: "18px", xl: "24px", "2xl": "32px" },
  buttons: { radius: "8px", primary_bg: "#5B2E1E", primary_text: "#FFFFFF", secondary_bg: "#D97706", secondary_text: "#2B1B12" },
};

function ThemesManager() {
  const [creating, setCreating] = useState(false);
  const [editing, setEditing] = useState<Theme | null>(null);
  const [previewing, setPreviewing] = useState<Theme | null>(null);

  const { data: themes, loading, refetch: load } = useAdminResource(getThemes, [] as Theme[]);

  const columns: Column<Theme>[] = [
    { key: "name", header: "Name", render: (t) => t.name },
    { key: "slug", header: "Slug", render: (t) => t.slug },
    { key: "colors", header: "Colors", render: (t) => (
      <div className="flex gap-1">
        <span className="h-5 w-5 rounded-full border border-stone-300" style={{ background: t.config.primary_color }} title="Primary" />
        <span className="h-5 w-5 rounded-full border border-stone-300" style={{ background: t.config.secondary_color }} title="Secondary" />
      </div>
    ) },
    { key: "active", header: "Active", render: (t) => (t.is_active ? "Yes" : "No") },
    {
      key: "actions",
      header: "",
      render: (t) => (
        <div className="flex flex-wrap gap-2">
          <button type="button" onClick={() => setPreviewing(t)} className="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300">Preview</button>
          <button type="button" onClick={() => setEditing(t)} className="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300">Edit</button>
          {t.is_active ? (
            <ConfirmButton
              label="Deactivate"
              variant="danger"
              confirmMessage={`Deactivate "${t.name}"? The storefront will fall back to its own default styling.`}
              onConfirm={async () => { await deactivateTheme(t.id); await load(); }}
            />
          ) : (
            <ConfirmButton
              label="Activate"
              confirmMessage={`Make "${t.name}" the active storefront theme?`}
              onConfirm={async () => { await activateTheme(t.id); await load(); }}
            />
          )}
        </div>
      ),
    },
  ];

  return (
    <div>
      <PageHeader
        title="Theme Manager"
        action={
          <button type="button" onClick={() => setCreating(true)} className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white dark:bg-amber-700">
            Add Theme
          </button>
        }
      />
      <DataTable columns={columns} rows={themes} rowKey={(t) => t.id} loading={loading} emptyMessage="No themes yet." />

      {creating ? (
        <ThemeModal
          title="Add Theme"
          initialName=""
          initialSlug=""
          initialConfig={DEFAULT_CONFIG}
          onClose={() => setCreating(false)}
          onSubmit={async (name, slug, config) => { await createTheme({ name, slug, config }); setCreating(false); await load(); }}
        />
      ) : null}
      {editing ? (
        <ThemeModal
          title={`Edit — ${editing.name}`}
          initialName={editing.name}
          initialSlug={editing.slug}
          initialConfig={editing.config}
          onClose={() => setEditing(null)}
          onSubmit={async (name, slug, config) => { await updateTheme(editing.id, { name, slug, config }); setEditing(null); await load(); }}
        />
      ) : null}
      {previewing ? <PreviewModal theme={previewing} onClose={() => setPreviewing(null)} /> : null}
    </div>
  );
}

function ThemeModal({
  title,
  initialName,
  initialSlug,
  initialConfig,
  onClose,
  onSubmit,
}: {
  title: string;
  initialName: string;
  initialSlug: string;
  initialConfig: ThemeConfig;
  onClose: () => void;
  onSubmit: (name: string, slug: string, config: ThemeConfig) => Promise<void>;
}) {
  const [name, setName] = useState(initialName);
  const [slug, setSlug] = useState(initialSlug);
  const [config, setConfig] = useState<ThemeConfig>(initialConfig);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]> | undefined>();
  const [submitting, setSubmitting] = useState(false);

  function setColor<K extends keyof ThemeConfig>(key: K, value: ThemeConfig[K]) {
    setConfig((c) => ({ ...c, [key]: value }));
  }

  async function handleSubmit() {
    setSubmitting(true);
    setError(null);
    try {
      await onSubmit(name, slug, config);
    } catch (err) {
      if (err instanceof ApiError) { setError(err.message); setFieldErrors(err.errors); } else setError("Something went wrong.");
      setSubmitting(false);
    }
  }

  return (
    <Modal title={title} onClose={onClose}>
      <FormError message={error} fieldErrors={fieldErrors} />
      <TextField id="theme-name" label="Name" required value={name} onChange={setName} />
      <TextField id="theme-slug" label="Slug" required value={slug} onChange={setSlug} />

      <h3 className="mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-stone-500">Background</h3>
      <div className="grid grid-cols-3 gap-2">
        <TextField id="background" label="Background" value={config.background} onChange={(v) => setColor("background", v)} />
        <TextField id="surface" label="Surface" value={config.surface} onChange={(v) => setColor("surface", v)} />
        <TextField id="text_color" label="Text" value={config.text_color} onChange={(v) => setColor("text_color", v)} />
      </div>

      <h3 className="mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-stone-500">Primary / Secondary colors</h3>
      <div className="grid grid-cols-2 gap-2">
        <TextField id="primary_color" label="Primary" value={config.primary_color} onChange={(v) => setColor("primary_color", v)} />
        <TextField id="secondary_color" label="Secondary" value={config.secondary_color} onChange={(v) => setColor("secondary_color", v)} />
      </div>

      <h3 className="mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-stone-500">Typography</h3>
      <TextField id="font_family" label="Body font family" value={config.typography.font_family} onChange={(v) => setColor("typography", { ...config.typography, font_family: v })} />
      <TextField id="heading_font_family" label="Heading font family" value={config.typography.heading_font_family} onChange={(v) => setColor("typography", { ...config.typography, heading_font_family: v })} />

      <h3 className="mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-stone-500">Font sizes</h3>
      <div className="grid grid-cols-5 gap-2">
        {(["sm", "base", "lg", "xl", "2xl"] as const).map((size) => (
          <TextField key={size} id={`font-size-${size}`} label={size} value={config.font_sizes[size]} onChange={(v) => setColor("font_sizes", { ...config.font_sizes, [size]: v })} />
        ))}
      </div>

      <h3 className="mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-stone-500">Buttons</h3>
      <TextField id="button_radius" label="Corner radius" value={config.buttons.radius} onChange={(v) => setColor("buttons", { ...config.buttons, radius: v })} />
      <div className="grid grid-cols-2 gap-2">
        <TextField id="primary_bg" label="Primary background" value={config.buttons.primary_bg} onChange={(v) => setColor("buttons", { ...config.buttons, primary_bg: v })} />
        <TextField id="primary_text" label="Primary text" value={config.buttons.primary_text} onChange={(v) => setColor("buttons", { ...config.buttons, primary_text: v })} />
        <TextField id="secondary_bg" label="Secondary background" value={config.buttons.secondary_bg} onChange={(v) => setColor("buttons", { ...config.buttons, secondary_bg: v })} />
        <TextField id="secondary_text" label="Secondary text" value={config.buttons.secondary_text} onChange={(v) => setColor("buttons", { ...config.buttons, secondary_text: v })} />
      </div>

      <ThemePreviewCard config={config} />

      <div className="mt-4 flex justify-end gap-2">
        <button type="button" onClick={onClose} className="rounded-md border border-stone-300 px-4 py-2 text-sm dark:border-stone-700">Cancel</button>
        <button type="button" disabled={submitting} onClick={handleSubmit} className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700">
          {submitting ? "Saving…" : "Save"}
        </button>
      </div>
    </Modal>
  );
}

/** A live-updating swatch of the config being edited — colors, typography, and both button styles rendered together. */
function ThemePreviewCard({ config }: { config: ThemeConfig }) {
  return (
    <div className="mt-4 rounded-lg border border-stone-200 p-4 dark:border-stone-800" style={{ background: config.background, color: config.text_color }}>
      <p className="mb-2 text-xs uppercase tracking-wide opacity-60">Live preview</p>
      <h4 style={{ fontFamily: config.typography.heading_font_family, fontSize: config.font_sizes.xl }} className="mb-1">
        Chocolate Fudge Cake
      </h4>
      <p style={{ fontFamily: config.typography.font_family, fontSize: config.font_sizes.base }} className="mb-3">
        Rich, handmade, delivered fresh.
      </p>
      <div className="mb-3 rounded-md p-3" style={{ background: config.surface }}>
        <span style={{ fontFamily: config.typography.font_family, fontSize: config.font_sizes.sm }}>Surface panel</span>
      </div>
      <div className="flex gap-2">
        <span
          className="px-4 py-2 text-sm"
          style={{ background: config.buttons.primary_bg, color: config.buttons.primary_text, borderRadius: config.buttons.radius }}
        >
          Add to Cart
        </span>
        <span
          className="px-4 py-2 text-sm"
          style={{ background: config.buttons.secondary_bg, color: config.buttons.secondary_text, borderRadius: config.buttons.radius }}
        >
          View Details
        </span>
      </div>
    </div>
  );
}

function PreviewModal({ theme, onClose }: { theme: Theme; onClose: () => void }) {
  return (
    <Modal title={`Preview — ${theme.name}`} onClose={onClose}>
      <ThemePreviewCard config={theme.config} />
    </Modal>
  );
}
