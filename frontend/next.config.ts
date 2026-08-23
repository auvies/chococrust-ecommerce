import type { NextConfig } from "next";

// Security fix (SECURITY_AUDIT.md): the backend already sets a strict
// CSP/X-Frame-Options/etc. on every API response (SecurityHeaders
// middleware), but this app is the one surface that actually serves HTML
// to a browser - it previously set none of its own headers, relying
// entirely on whatever the hosting platform might add by default. `self`
// covers same-origin scripts/styles/fonts (next/font self-hosts Google
// Fonts at build time - no runtime connection to fonts.googleapis.com is
// needed); `connect-src` additionally allows only this app's own
// configured backend API origin, read from the same env var the API
// client itself uses, so it never silently drifts from the real backend.
const apiOrigin = (() => {
  try {
    return new URL(process.env.NEXT_PUBLIC_API_BASE_URL ?? "").origin;
  } catch {
    return "";
  }
})();

const connectSrc = ["'self'", apiOrigin].filter(Boolean).join(" ");
const isDev = process.env.NODE_ENV !== "production";

const securityHeaders = [
  { key: "X-Frame-Options", value: "DENY" },
  { key: "X-Content-Type-Options", value: "nosniff" },
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  { key: "Permissions-Policy", value: "geolocation=(), microphone=(), camera=()" },
  {
    key: "Content-Security-Policy",
    value: [
      "default-src 'self'",
      // 'unsafe-inline' is required here without a per-request nonce
      // (App Router injects inline hydration/streaming bootstrap scripts
      // by design - confirmed by testing 'self'-only in a browser, which
      // broke the app outright). A nonce-based CSP would remove this but
      // needs request middleware generating and threading a fresh nonce
      // through every response, which is a larger change than this
      // security-hardening pass covers - everything else below is still
      // real, meaningful restriction (no object embedding, no framing,
      // connect-src pinned to this app's own configured API origin).
      // 'unsafe-eval' is dev-only (confirmed the same way): React/Turbopack
      // use eval() in development for stack-trace reconstruction - "React
      // will never use eval() in production mode" is React's own console
      // message when this fires, so the production CSP omits it entirely.
      `script-src 'self' 'unsafe-inline'${isDev ? " 'unsafe-eval'" : ""}`,
      "style-src 'self' 'unsafe-inline'",
      "img-src 'self' data: https:",
      "font-src 'self' data:",
      `connect-src ${connectSrc}`,
      "frame-ancestors 'none'",
      "base-uri 'self'",
      "form-action 'self'",
    ].join("; "),
  },
];

const nextConfig: NextConfig = {
  async headers() {
    return [{ source: "/:path*", headers: securityHeaders }];
  },
};

export default nextConfig;
