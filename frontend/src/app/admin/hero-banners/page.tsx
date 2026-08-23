"use client";

import { useState } from "react";
import { RequirePermission } from "@/components/admin/RequirePermission";
import { PageHeader } from "@/components/admin/ui/PageHeader";
import { DataTable, type Column } from "@/components/admin/ui/DataTable";
import { ConfirmButton } from "@/components/admin/ui/ConfirmButton";
import { Modal } from "@/components/admin/ui/Modal";
import { FormError } from "@/components/admin/ui/FormError";
import { CheckboxField, NumberField, TextField } from "@/components/admin/ui/fields";
import { useAdminList, useAdminResource } from "@/lib/hooks/useAdminList";
import { getHeroBanners } from "@/lib/api/content";
import {
  createHeroBanner,
  updateHeroBanner,
  deleteHeroBanner,
  restoreHeroBanner,
  reorderHeroBanners,
  uploadHeroBannerImage,
  getArchivedHeroBanners,
  type HeroBannerPayload,
} from "@/lib/api/admin/content";
import { ApiError } from "@/lib/api/client";
import { PERMISSIONS } from "@/lib/permissions";
import type { HeroBanner } from "@/types/api";

export default function HeroBannersPage() {
  return (
    <RequirePermission anyOf={[PERMISSIONS.contentManage]}>
      <HeroBannersManager />
    </RequirePermission>
  );
}

const emptyForm: HeroBannerPayload = { title: "", subtitle: "", image_url: "", link_url: "", cta_text: "", sort_order: 0, is_active: true };

function HeroBannersManager() {
  const { data, loading, refetch } = useAdminList(async () => ({
    data: await getHeroBanners(),
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, from: null, last_page: 1, path: "", per_page: 100, to: null, total: 0 },
  }));
  const [creating, setCreating] = useState(false);
  const [editing, setEditing] = useState<HeroBanner | null>(null);
  const [uploadingFor, setUploadingFor] = useState<HeroBanner | null>(null);
  const [previewing, setPreviewing] = useState<HeroBanner | null>(null);
  const [showArchived, setShowArchived] = useState(false);

  const banners = [...data].sort((a, b) => a.sort_order - b.sort_order);

  async function move(index: number, direction: -1 | 1) {
    const target = index + direction;
    if (target < 0 || target >= banners.length) return;
    const reordered = [...banners];
    [reordered[index], reordered[target]] = [reordered[target], reordered[index]];
    await reorderHeroBanners(reordered.map((b) => b.id));
    await refetch();
  }

  const columns: Column<HeroBanner>[] = [
    {
      key: "image",
      header: "",
      render: (b) =>
        b.image_url ? (
          // eslint-disable-next-line @next/next/no-img-element -- admin thumbnail of an uploaded/external URL
          <img src={b.image_url} alt={b.alt_text ?? b.title} className="h-10 w-16 rounded object-cover" />
        ) : (
          <span className="block h-10 w-16 rounded bg-stone-100 text-center text-[10px] leading-10 text-stone-400 dark:bg-stone-800">No image</span>
        ),
    },
    { key: "title", header: "Title", render: (b) => b.title },
    { key: "cta", header: "CTA", render: (b) => b.cta_text ?? "—" },
    { key: "sort", header: "Sort", render: (b) => b.sort_order },
    { key: "active", header: "Active", render: (b) => (b.is_active ? "Yes" : "No") },
    { key: "window", header: "Window", render: (b) => (b.starts_at || b.ends_at ? `${b.starts_at ?? "…"} – ${b.ends_at ?? "…"}` : "Always") },
    {
      key: "actions",
      header: "",
      render: (b) => {
        const index = banners.findIndex((x) => x.id === b.id);
        return (
          <div className="flex flex-wrap gap-2">
            <button type="button" onClick={() => move(index, -1)} disabled={index === 0} className="rounded-md bg-stone-100 px-2 py-1 text-xs disabled:opacity-40 dark:bg-stone-800">↑</button>
            <button type="button" onClick={() => move(index, 1)} disabled={index === banners.length - 1} className="rounded-md bg-stone-100 px-2 py-1 text-xs disabled:opacity-40 dark:bg-stone-800">↓</button>
            <button type="button" onClick={() => setPreviewing(b)} className="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300">Preview</button>
            <button type="button" onClick={() => setUploadingFor(b)} className="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300">Image</button>
            <button type="button" onClick={() => setEditing(b)} className="rounded-md bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300">Edit</button>
            <ConfirmButton
              label="Archive"
              variant="danger"
              confirmMessage={`Archive banner "${b.title}"? It can be restored later.`}
              onConfirm={async () => { await deleteHeroBanner(b.id); await refetch(); }}
            />
          </div>
        );
      },
    },
  ];

  return (
    <div>
      <PageHeader
        title="Hero Banner Manager"
        action={
          <div className="flex gap-2">
            <button type="button" onClick={() => setShowArchived((s) => !s)} className="rounded-md border border-stone-300 px-4 py-2 text-sm dark:border-stone-700">
              {showArchived ? "Hide archived" : "Show archived"}
            </button>
            <button type="button" onClick={() => setCreating(true)} className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white dark:bg-amber-700">
              Add Banner
            </button>
          </div>
        }
      />
      <DataTable columns={columns} rows={banners} rowKey={(b) => b.id} loading={loading} emptyMessage="No hero banners yet." />

      {showArchived ? <ArchivedBanners onRestored={refetch} /> : null}

      {creating ? (
        <BannerModal title="Add Banner" initial={emptyForm} onClose={() => setCreating(false)} onSubmit={async (p) => { await createHeroBanner(p); setCreating(false); await refetch(); }} />
      ) : null}
      {editing ? (
        <BannerModal
          title="Edit Banner"
          initial={{
            title: editing.title, subtitle: editing.subtitle ?? "", image_url: editing.image_url ?? "",
            link_url: editing.link_url ?? "", cta_text: editing.cta_text ?? "", sort_order: editing.sort_order, is_active: editing.is_active,
          }}
          onClose={() => setEditing(null)}
          onSubmit={async (p) => { await updateHeroBanner(editing.id, p); setEditing(null); await refetch(); }}
        />
      ) : null}
      {uploadingFor ? (
        <ImageUploadModal banner={uploadingFor} onClose={() => setUploadingFor(null)} onUploaded={async () => { setUploadingFor(null); await refetch(); }} />
      ) : null}
      {previewing ? <PreviewModal banner={previewing} onClose={() => setPreviewing(null)} /> : null}
    </div>
  );
}

function ArchivedBanners({ onRestored }: { onRestored: () => Promise<void> }) {
  const { data: archived, loading, refetch } = useAdminResource(getArchivedHeroBanners, [] as HeroBanner[]);

  return (
    <section className="mt-6">
      <h2 className="mb-2 text-sm font-semibold text-stone-900 dark:text-stone-100">Archived</h2>
      {loading ? (
        <p className="text-sm text-stone-500">Loading…</p>
      ) : archived.length === 0 ? (
        <p className="text-sm text-stone-500">Nothing archived.</p>
      ) : (
        <ul className="flex flex-col gap-2">
          {archived.map((b) => (
            <li key={b.id} className="flex items-center justify-between rounded-md border border-stone-200 p-3 text-sm dark:border-stone-800">
              <span>{b.title}</span>
              <ConfirmButton
                label="Restore"
                confirmMessage={`Restore banner "${b.title}"?`}
                onConfirm={async () => { await restoreHeroBanner(b.id); await Promise.all([refetch(), onRestored()]); }}
              />
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

function BannerModal({ title, initial, onClose, onSubmit }: { title: string; initial: HeroBannerPayload; onClose: () => void; onSubmit: (p: HeroBannerPayload) => Promise<void> }) {
  const [form, setForm] = useState<HeroBannerPayload>(initial);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]> | undefined>();
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit() {
    setSubmitting(true);
    setError(null);
    try {
      await onSubmit(form);
    } catch (err) {
      if (err instanceof ApiError) { setError(err.message); setFieldErrors(err.errors); } else setError("Something went wrong.");
      setSubmitting(false);
    }
  }

  return (
    <Modal title={title} onClose={onClose}>
      <FormError message={error} fieldErrors={fieldErrors} />
      <TextField id="title" label="Title" required value={form.title} onChange={(title) => setForm({ ...form, title })} />
      <TextField id="subtitle" label="Subtitle" value={form.subtitle ?? ""} onChange={(subtitle) => setForm({ ...form, subtitle })} />
      <p className="mb-3 text-xs text-stone-500">Upload the image via the row&apos;s &quot;Image&quot; action after saving.</p>
      <TextField id="link_url" label="Link URL (where the CTA button goes)" value={form.link_url ?? ""} onChange={(link_url) => setForm({ ...form, link_url })} />
      <TextField id="cta_text" label="CTA button label" value={form.cta_text ?? ""} onChange={(cta_text) => setForm({ ...form, cta_text })} placeholder="Shop Now" />
      <NumberField id="sort_order" label="Sort order" value={form.sort_order ?? 0} onChange={(sort_order) => setForm({ ...form, sort_order: sort_order === "" ? 0 : sort_order })} />
      <CheckboxField id="is_active" label="Active" checked={form.is_active ?? true} onChange={(is_active) => setForm({ ...form, is_active })} />
      <div className="mt-4 flex justify-end gap-2">
        <button type="button" onClick={onClose} className="rounded-md border border-stone-300 px-4 py-2 text-sm dark:border-stone-700">Cancel</button>
        <button type="button" disabled={submitting} onClick={handleSubmit} className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700">
          {submitting ? "Saving…" : "Save"}
        </button>
      </div>
    </Modal>
  );
}

function ImageUploadModal({ banner, onClose, onUploaded }: { banner: HeroBanner; onClose: () => void; onUploaded: () => Promise<void> }) {
  const [desktop, setDesktop] = useState<File | null>(null);
  const [mobile, setMobile] = useState<File | null>(null);
  const [altText, setAltText] = useState(banner.alt_text ?? "");
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]> | undefined>();
  const [uploading, setUploading] = useState(false);

  async function handleUpload() {
    setUploading(true);
    setError(null);
    try {
      await uploadHeroBannerImage(banner.id, { desktop: desktop ?? undefined, mobile: mobile ?? undefined }, altText || undefined);
      await onUploaded();
    } catch (err) {
      if (err instanceof ApiError) { setError(err.message); setFieldErrors(err.errors); } else setError("Something went wrong.");
      setUploading(false);
    }
  }

  return (
    <Modal title={`Image — ${banner.title}`} onClose={onClose}>
      <FormError message={error} fieldErrors={fieldErrors} />
      <div className="mb-3">
        <label htmlFor="desktop-image" className="mb-1 block text-sm font-medium text-stone-700 dark:text-stone-300">Desktop image (JPEG/PNG/WebP, up to 8MB)</label>
        <input id="desktop-image" type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => setDesktop(e.target.files?.[0] ?? null)} className="block w-full text-sm" />
      </div>
      <div className="mb-3">
        <label htmlFor="mobile-image" className="mb-1 block text-sm font-medium text-stone-700 dark:text-stone-300">Mobile image (different crop, optional)</label>
        <input id="mobile-image" type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => setMobile(e.target.files?.[0] ?? null)} className="block w-full text-sm" />
      </div>
      <TextField id="alt-text" label="Alt text" value={altText} onChange={setAltText} />
      <div className="mt-4 flex justify-end gap-2">
        <button type="button" onClick={onClose} className="rounded-md border border-stone-300 px-4 py-2 text-sm dark:border-stone-700">Cancel</button>
        <button type="button" disabled={uploading || (!desktop && !mobile)} onClick={handleUpload} className="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60 dark:bg-amber-700">
          {uploading ? "Uploading…" : "Upload"}
        </button>
      </div>
    </Modal>
  );
}

function PreviewModal({ banner, onClose }: { banner: HeroBanner; onClose: () => void }) {
  return (
    <Modal title={`Preview — ${banner.title}`} onClose={onClose}>
      <div className="relative overflow-hidden rounded-lg bg-stone-900">
        {banner.image_url ? (
          // eslint-disable-next-line @next/next/no-img-element -- live preview of an uploaded/external URL
          <img src={banner.image_url} alt={banner.alt_text ?? banner.title} className="h-48 w-full object-cover opacity-80" />
        ) : (
          <div className="flex h-48 items-center justify-center text-sm text-stone-400">No image uploaded yet</div>
        )}
        <div className="absolute inset-0 flex flex-col items-start justify-center gap-2 bg-black/30 p-6">
          <h3 className="text-xl font-semibold text-white">{banner.title}</h3>
          {banner.subtitle ? <p className="text-sm text-white/90">{banner.subtitle}</p> : null}
          {banner.cta_text ? (
            <span className="mt-2 rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white">{banner.cta_text}</span>
          ) : null}
        </div>
      </div>
      <p className="mt-3 text-xs text-stone-500">Desktop crop shown above{banner.mobile_image_url ? " — a separate mobile crop is also set." : "."}</p>
    </Modal>
  );
}
