import { apiFetch } from "@/lib/api/client";
import type { ApiEnvelope, SystemSetting } from "@/types/api";

/**
 * Public read only ever returns rows with `is_public = true` (server-
 * enforced) — safe for storefront use (e.g. contact details, store hours).
 * Not paginated server-side (a plain `->get()` collection), unlike the
 * catalog/review index endpoints.
 */
export async function getPublicSettings(): Promise<Record<string, string | null>> {
  const { data } = await apiFetch<ApiEnvelope<SystemSetting[]>>("/v1/settings", {
    cache: "no-store",
  });
  return Object.fromEntries(data.map((setting) => [setting.key, setting.value]));
}
