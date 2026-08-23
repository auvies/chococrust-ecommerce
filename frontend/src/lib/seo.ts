import type { Metadata } from "next";
import { env } from "@/lib/env";
import type { SeoMetadata } from "@/types/api";

export interface SeoFallback {
  title: string;
  description?: string | null;
  path: string;
  image?: string | null;
}

/**
 * Builds Next.js `Metadata` from the backend's per-entity SEO row when one
 * exists (`GET /seo/{type}/{id}`), falling back to sensible defaults derived
 * from the entity itself so every page still has a title/description even
 * before a content manager has configured SEO metadata for it.
 */
export function buildMetadata(seo: SeoMetadata | null, fallback: SeoFallback): Metadata {
  const title = seo?.meta_title || fallback.title;
  const description = seo?.meta_description || fallback.description || undefined;
  const canonical = seo?.canonical_url || `${env.siteUrl}${fallback.path}`;
  const image = seo?.og_image_url || fallback.image || undefined;

  return {
    title,
    description,
    keywords: seo?.meta_keywords || undefined,
    alternates: { canonical },
    openGraph: {
      title,
      description,
      url: canonical,
      images: image ? [{ url: image }] : undefined,
    },
  };
}
