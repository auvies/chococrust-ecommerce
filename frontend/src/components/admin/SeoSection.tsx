"use client";

import { useEffect, useState } from "react";
import { FormError } from "@/components/admin/ui/FormError";
import { TextField } from "@/components/admin/ui/fields";
import { getSeoMetadataAdmin, upsertSeoMetadata, type SeoPayload } from "@/lib/api/admin/content";
import { ApiError } from "@/lib/api/client";

/** Shared by the Category and Product managers — same backend endpoint (`/v1/seo/{type}/{id}`), only `type` differs. */
export function SeoSection({ entityType, entityId }: { entityType: "category" | "product"; entityId: number }) {
  const [form, setForm] = useState<SeoPayload>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    getSeoMetadataAdmin(entityType, entityId)
      .then((seo) => setForm(seo ?? {}))
      .finally(() => setLoading(false));
  }, [entityType, entityId]);

  async function handleSave() {
    setSaving(true);
    setError(null);
    setSaved(false);
    try {
      await upsertSeoMetadata(entityType, entityId, form);
      setSaved(true);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Something went wrong.");
    } finally {
      setSaving(false);
    }
  }

  if (loading) return null;

  return (
    <section className="max-w-xl rounded-lg border border-stone-200 p-4 dark:border-stone-800">
      <h2 className="mb-3 text-sm font-semibold text-stone-900 dark:text-stone-100">SEO</h2>
      <FormError message={error} />
      <TextField id="meta_title" label="Meta title" value={form.meta_title ?? ""} onChange={(meta_title) => setForm({ ...form, meta_title })} />
      <TextField id="meta_description" label="Meta description" value={form.meta_description ?? ""} onChange={(meta_description) => setForm({ ...form, meta_description })} />
      <TextField id="meta_keywords" label="Meta keywords" value={form.meta_keywords ?? ""} onChange={(meta_keywords) => setForm({ ...form, meta_keywords })} />
      <TextField id="canonical_url" label="Canonical URL" value={form.canonical_url ?? ""} onChange={(canonical_url) => setForm({ ...form, canonical_url })} />
      <TextField id="og_title" label="Open Graph title" value={form.og_title ?? ""} onChange={(og_title) => setForm({ ...form, og_title })} />
      <TextField id="og_description" label="Open Graph description" value={form.og_description ?? ""} onChange={(og_description) => setForm({ ...form, og_description })} />
      <TextField id="og_image_url" label="OG image URL" value={form.og_image_url ?? ""} onChange={(og_image_url) => setForm({ ...form, og_image_url })} />
      <div className="flex items-center gap-3">
        <button
          type="button"
          onClick={handleSave}
          disabled={saving}
          className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700"
        >
          {saving ? "Saving…" : "Save SEO"}
        </button>
        {saved ? <span className="text-sm text-green-700 dark:text-green-400">Saved.</span> : null}
      </div>
    </section>
  );
}
