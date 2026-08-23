import { env } from "@/lib/env";
import { logger } from "@/lib/logger";

/**
 * Foundation-level API client. No business endpoints are defined here yet —
 * this only establishes the base URL, error shape, and logging so future
 * feature code has a single, consistent way to call the backend.
 *
 * The frontend never calls third-party services directly; everything goes
 * through this client to the Laravel backend (CLAUDE.md §1).
 */

export class ApiError extends Error {
  readonly status: number;
  /** Laravel's per-field validation messages on a 422 — already sanitized/user-facing server-side. */
  readonly errors?: Record<string, string[]>;

  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
  }
}

export interface ApiFetchOptions extends Omit<RequestInit, "body"> {
  body?: unknown;
}

const MUTATING_METHODS = new Set(["POST", "PUT", "PATCH", "DELETE"]);

/** Reads a cookie by name in the browser. No-op (undefined) during SSR. */
function readCookie(name: string): string | undefined {
  if (typeof document === "undefined") return undefined;
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : undefined;
}

export async function apiFetch<T>(path: string, options: ApiFetchOptions = {}): Promise<T> {
  const url = `${env.apiBaseUrl}${path.startsWith("/") ? path : `/${path}`}`;
  const { body, headers, ...rest } = options;
  const method = (rest.method ?? "GET").toUpperCase();

  // The CSRF double-submit cookie is deliberately non-httpOnly so frontend
  // JS can read it and echo it back as a header (CLAUDE.md §7 / ADR 0003) —
  // only relevant for authenticated, state-changing admin requests.
  const csrfToken = MUTATING_METHODS.has(method) ? readCookie("cc_csrf_token") : undefined;

  const response = await fetch(url, {
    ...rest,
    // Session cookies live on the backend's own origin; every request
    // needs to send them for authenticated admin calls to work, and this
    // is a no-op for the public storefront's unauthenticated reads.
    credentials: "include",
    headers: {
      Accept: "application/json",
      ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
      ...(csrfToken ? { "X-CSRF-Token": csrfToken } : {}),
      ...headers,
    },
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });

  if (!response.ok) {
    // 4xx bodies from this backend are already sanitized/user-facing
    // (validation messages, "not found", etc. — CLAUDE.md §16's redaction
    // happens server-side in Phase 04's exception handler), so it's safe
    // to relay `message`/`errors` as-is. Anything else falls back to a
    // generic message; full detail is logged here either way.
    logger.error("API request failed", { url, status: response.status });

    let message = `Request to ${path} failed`;
    let errors: Record<string, string[]> | undefined;
    try {
      const problem = (await response.json()) as { message?: string; errors?: Record<string, string[]> };
      if (problem.message) message = problem.message;
      errors = problem.errors;
    } catch {
      // Non-JSON error body (e.g. a gateway timeout page) — keep the generic message.
    }

    throw new ApiError(message, response.status, errors);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return (await response.json()) as T;
}
