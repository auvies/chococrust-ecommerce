/**
 * Centralized, validated access to environment configuration.
 *
 * Only `NEXT_PUBLIC_*` variables may be read here — anything else is a
 * server-only secret and must never be imported from client-rendered code
 * (CLAUDE.md §6).
 */

function requireEnv(name: string, value: string | undefined): string {
  if (!value || value.trim() === "") {
    throw new Error(
      `Missing required environment variable: ${name}. Check your .env.local against .env.example.`,
    );
  }
  return value;
}

export const env = {
  apiBaseUrl: requireEnv("NEXT_PUBLIC_API_BASE_URL", process.env.NEXT_PUBLIC_API_BASE_URL),
  appEnv: process.env.NEXT_PUBLIC_APP_ENV ?? "development",
  // Used only for building absolute canonical/OG URLs and the sitemap —
  // optional so local dev doesn't need it set, unlike the API base URL.
  siteUrl: (process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000").replace(/\/$/, ""),
} as const;
