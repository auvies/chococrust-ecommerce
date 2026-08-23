import { apiFetch, ApiError, type ApiFetchOptions } from "@/lib/api/client";
import { refreshSession } from "@/lib/api/auth";

/**
 * Admin calls sit behind a short-lived (15 min) access token. Rather than
 * force staff to re-login mid-task, a single 401 triggers one refresh
 * attempt (rotating the refresh cookie) and one retry of the original
 * request — after that, the caller is genuinely logged out and the error
 * propagates so the UI can redirect to /login.
 */
export async function adminFetch<T>(path: string, options: ApiFetchOptions = {}): Promise<T> {
  try {
    return await apiFetch<T>(path, options);
  } catch (error) {
    if (error instanceof ApiError && error.status === 401 && !path.startsWith("/v1/auth/")) {
      await refreshSession();
      return apiFetch<T>(path, options);
    }
    throw error;
  }
}
