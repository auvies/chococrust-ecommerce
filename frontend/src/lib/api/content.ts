import { apiFetch } from "@/lib/api/client";
import type { ApiEnvelope, HeroBanner, HomepageSection, SeoMetadata } from "@/types/api";

export async function getHeroBanners(): Promise<HeroBanner[]> {
  const { data } = await apiFetch<ApiEnvelope<HeroBanner[]>>("/v1/hero-banners", {
    cache: "no-store",
  });
  return data;
}

export async function getHomepageSections(): Promise<HomepageSection[]> {
  const { data } = await apiFetch<ApiEnvelope<HomepageSection[]>>("/v1/homepage-sections", {
    cache: "no-store",
  });
  return data;
}

/** `type` is allow-listed server-side to "category" | "product" — see SeoMetadataController. */
export async function getSeoMetadata(
  type: "category" | "product",
  id: number,
): Promise<SeoMetadata | null> {
  const { data } = await apiFetch<ApiEnvelope<SeoMetadata | null>>(`/v1/seo/${type}/${id}`, {
    cache: "no-store",
  });
  return data;
}
