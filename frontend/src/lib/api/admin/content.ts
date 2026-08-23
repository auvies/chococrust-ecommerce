import { adminFetch } from "@/lib/api/admin/client";
import { uploadFormData } from "@/lib/api/admin/upload";
import type { ApiEnvelope, HeroBanner, HomepageSection, SeoMetadata, Theme, ThemeConfig } from "@/types/api";

// --- Hero banners ---
export interface HeroBannerPayload {
  title: string;
  subtitle?: string | null;
  image_url: string;
  mobile_image_url?: string | null;
  alt_text?: string | null;
  link_url?: string | null;
  cta_text?: string | null;
  sort_order?: number;
  is_active?: boolean;
  starts_at?: string | null;
  ends_at?: string | null;
}

export async function getHeroBanner(id: number): Promise<HeroBanner> {
  const { data } = await adminFetch<ApiEnvelope<HeroBanner>>(`/v1/hero-banners/${id}`);
  return data;
}

export async function getArchivedHeroBanners(): Promise<HeroBanner[]> {
  const { data } = await adminFetch<ApiEnvelope<HeroBanner[]>>("/v1/hero-banners?archived=1");
  return data;
}

export async function createHeroBanner(payload: HeroBannerPayload): Promise<HeroBanner> {
  const { data } = await adminFetch<ApiEnvelope<HeroBanner>>("/v1/hero-banners", { method: "POST", body: payload });
  return data;
}

export async function updateHeroBanner(id: number, payload: Partial<HeroBannerPayload>): Promise<HeroBanner> {
  const { data } = await adminFetch<ApiEnvelope<HeroBanner>>(`/v1/hero-banners/${id}`, { method: "PUT", body: payload });
  return data;
}

/** Secure file upload for the desktop and/or mobile crop - re-uploading here IS how an admin replaces an image. */
export async function uploadHeroBannerImage(
  id: number,
  files: { desktop?: File; mobile?: File },
  altText?: string,
): Promise<HeroBanner> {
  const form = new FormData();
  if (files.desktop) form.set("desktop_image", files.desktop);
  if (files.mobile) form.set("mobile_image", files.mobile);
  if (altText) form.set("alt_text", altText);

  return uploadFormData<HeroBanner>(`/v1/hero-banners/${id}/image`, form);
}

/** Sends the full desired id order; the backend sets each banner's sort_order to its position. */
export async function reorderHeroBanners(order: number[]): Promise<HeroBanner[]> {
  const { data } = await adminFetch<ApiEnvelope<HeroBanner[]>>("/v1/hero-banners/reorder", {
    method: "PATCH",
    body: { order },
  });
  return data;
}

/** Archive (soft-delete) - see restoreHeroBanner() to undo. */
export async function deleteHeroBanner(id: number): Promise<void> {
  await adminFetch<void>(`/v1/hero-banners/${id}`, { method: "DELETE" });
}

export async function restoreHeroBanner(id: number): Promise<HeroBanner> {
  const { data } = await adminFetch<ApiEnvelope<HeroBanner>>(`/v1/hero-banners/${id}/restore`, { method: "POST" });
  return data;
}

// --- Homepage sections ---
export interface HomepageSectionPayload {
  type: string;
  title?: string | null;
  config?: Record<string, unknown> | null;
  sort_order?: number;
  is_active?: boolean;
}

export async function createHomepageSection(payload: HomepageSectionPayload): Promise<HomepageSection> {
  const { data } = await adminFetch<ApiEnvelope<HomepageSection>>("/v1/homepage-sections", {
    method: "POST",
    body: payload,
  });
  return data;
}

export async function updateHomepageSection(
  id: number,
  payload: Partial<HomepageSectionPayload>,
): Promise<HomepageSection> {
  const { data } = await adminFetch<ApiEnvelope<HomepageSection>>(`/v1/homepage-sections/${id}`, {
    method: "PUT",
    body: payload,
  });
  return data;
}

export async function deleteHomepageSection(id: number): Promise<void> {
  await adminFetch<void>(`/v1/homepage-sections/${id}`, { method: "DELETE" });
}

// --- Themes ---
export async function getThemes(): Promise<Theme[]> {
  const { data } = await adminFetch<ApiEnvelope<Theme[]>>("/v1/themes");
  return data;
}

export async function getTheme(id: number): Promise<Theme> {
  const { data } = await adminFetch<ApiEnvelope<Theme>>(`/v1/themes/${id}`);
  return data;
}

export async function createTheme(payload: { name: string; slug: string; config: ThemeConfig; is_active?: boolean }): Promise<Theme> {
  const { data } = await adminFetch<ApiEnvelope<Theme>>("/v1/themes", { method: "POST", body: payload });
  return data;
}

export async function updateTheme(id: number, payload: Partial<{ name: string; slug: string; config: ThemeConfig }>): Promise<Theme> {
  const { data } = await adminFetch<ApiEnvelope<Theme>>(`/v1/themes/${id}`, { method: "PUT", body: payload });
  return data;
}

export async function activateTheme(id: number): Promise<Theme> {
  const { data } = await adminFetch<ApiEnvelope<Theme>>(`/v1/themes/${id}/activate`, { method: "POST" });
  return data;
}

/** Turns the active theme off without activating another - the storefront falls back to its own default styling. */
export async function deactivateTheme(id: number): Promise<Theme> {
  const { data } = await adminFetch<ApiEnvelope<Theme>>(`/v1/themes/${id}/deactivate`, { method: "POST" });
  return data;
}

// --- SEO metadata ---
export async function getSeoMetadataAdmin(type: "category" | "product", id: number): Promise<SeoMetadata | null> {
  const { data } = await adminFetch<ApiEnvelope<SeoMetadata | null>>(`/v1/seo/${type}/${id}`);
  return data;
}

export interface SeoPayload {
  meta_title?: string | null;
  meta_description?: string | null;
  meta_keywords?: string | null;
  canonical_url?: string | null;
  og_title?: string | null;
  og_description?: string | null;
  og_image_url?: string | null;
}

export async function upsertSeoMetadata(
  type: "category" | "product",
  id: number,
  payload: SeoPayload,
): Promise<SeoMetadata> {
  const { data } = await adminFetch<ApiEnvelope<SeoMetadata>>(`/v1/seo/${type}/${id}`, { method: "PUT", body: payload });
  return data;
}
