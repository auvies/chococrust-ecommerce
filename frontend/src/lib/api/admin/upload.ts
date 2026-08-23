import { ApiError } from "@/lib/api/client";
import { env } from "@/lib/env";
import type { ApiEnvelope } from "@/types/api";

function readCsrfCookie(): string | undefined {
  const match = document.cookie.match(/(?:^|; )cc_csrf_token=([^;]*)/);
  return match ? decodeURIComponent(match[1]) : undefined;
}

/**
 * Shared by every multipart upload (product media, category images, hero
 * banner desktop/mobile images) — `FormData` bodies bypass `apiFetch`'s
 * JSON handling, so this builds the request directly, still with the same
 * credentials/CSRF behavior every other admin mutation gets.
 */
export async function uploadFormData<T>(path: string, form: FormData, method: "POST" | "PUT" = "POST"): Promise<T> {
  const response = await fetch(`${env.apiBaseUrl}${path}`, {
    method,
    credentials: "include",
    headers: { Accept: "application/json", "X-CSRF-Token": readCsrfCookie() ?? "" },
    body: form,
  });

  if (!response.ok) {
    let message = "Upload failed";
    let errors: Record<string, string[]> | undefined;
    try {
      const body = await response.json();
      message = body.message ?? message;
      errors = body.errors;
    } catch {
      // non-JSON body, keep generic message
    }
    throw new ApiError(message, response.status, errors);
  }

  const body = (await response.json()) as ApiEnvelope<T>;
  return body.data;
}
