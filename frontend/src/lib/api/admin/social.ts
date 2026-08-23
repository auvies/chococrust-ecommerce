import { adminFetch } from "@/lib/api/admin/client";
import type { ApiEnvelope, SocialAccountStatus } from "@/types/api";

/**
 * Facebook/Instagram are prepared, not built (no account is connected yet
 * - see backend/app/Http/Controllers/Api/V1/Social/SocialAccountController.php).
 * This only ever returns a connected: true/false status per platform plus
 * a couple of non-secret identifiers - never a token or app secret.
 */
export async function getSocialAccountStatus(): Promise<SocialAccountStatus[]> {
  const { data } = await adminFetch<ApiEnvelope<SocialAccountStatus[]>>("/v1/social/accounts");
  return data;
}
