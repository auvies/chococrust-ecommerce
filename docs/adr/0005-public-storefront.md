# ADR 0005: Public Storefront Frontend

**Status:** Accepted
**Date:** 2026-08-22

## Context

Phase 05 needed a public, customer-facing Next.js storefront on top of the Phase 04 API: home, category navigation, product pages, search, and informational pages (About/Contact/FAQ), mobile-first and SEO-routed, with an explicit instruction not to hardcode products or categories and not to build cart/checkout yet.

## Decisions

### Everything renders per request, not statically generated

The root layout (`frontend/src/app/layout.tsx`) sets `export const dynamic = "force-dynamic"`. The header fetches categories and the footer fetches public settings on every request, so the whole route tree is dynamic by necessity - this also means `next build` never needs a live backend to succeed (verified: the build passes with the backend stopped), since Next.js doesn't attempt to prerender a route it already knows is dynamic. The alternative (static generation with revalidation) would have meant the build could fail depending on backend availability at deploy time, which is a worse failure mode for a storefront than always rendering fresh.

### "Best Sellers" and "Offers" are derived from real signals, not invented

Phase 04 confirmed the backend has no sales-count or "best seller" column - only a real `is_featured` flag and a `compare_at_price` field per variant. Rather than hardcode a curated product list (which the task explicitly forbade) or fabricate a ranking, [`src/lib/homepage.ts`](../../frontend/src/lib/homepage.ts):

- **Offers**: scans a pool of active products for variants where `compare_at_price > price` and ranks by markdown percentage - a direct, honest reading of real data.
- **Best Sellers**: ranks the same pool by live approved-review count per product, a genuine (if imperfect) public popularity signal. This is documented in code and here as a proxy, not a claim of exact sales ranking - the right long-term fix is a real sales-count field or order-aggregation endpoint on the backend, out of scope for a frontend-only phase.
- **Testimonials**: reuses the review data already fetched for the best-sellers ranking (top-rated reviews with a title/body), rather than issuing a third round of requests for the same underlying data.

### One shared candidate pool per homepage load, found by testing against a real backend

The first implementation fetched a separate product list per section (featured, best-sellers pool, offers pool) plus one review request per best-seller candidate. Manually verifying the homepage against a locally seeded backend (per CLAUDE.md's "start the dev server and use the feature in a browser" requirement) surfaced `429 Too Many Requests` responses from the backend's general API rate limiter (`throttle:api`, 60/min/IP - CLAUDE.md §14) on a single page load. `getHomepageProductData()` was refactored to fetch one product pool and reuse it for best-sellers ranking, testimonials, and offers, cutting a single homepage load's request count roughly in half. The header's and homepage's category fetches remain two separate requests (they're structurally independent Server Components); this is a known, minor, documented inefficiency rather than a correctness problem, since 60/min per visitor is still generous for normal browsing.

### Every data call degrades independently

`src/lib/api/*.ts` and `src/lib/homepage.ts` each catch their own fetch failures and return an empty/fallback value. A slow or broken endpoint empties one homepage section (e.g. "No active offers right now") instead of tripping the page-level error boundary, per CLAUDE.md §16 ("failures in non-critical paths... degrade gracefully"). This was exercised directly during the rate-limit issue above: every section showed its empty state instead of the page crashing.

### Id+slug routing, because the backend only binds by id

`backend/routes/api/catalog.php` resolves `{product}`/`{category}` via Laravel's default route-model-binding key (`id`) - there is no `getRouteKeyName()` override, confirmed by reading the models and routes directly. Rather than pretend the API supports slug lookups it doesn't, product and category URLs are `/{id}/{slug}` (e.g. `/products/42/chocolate-cake`): the id is what's actually fetched from the backend, the slug exists for readability and SEO and is kept in sync via a `redirect()` if a product's slug ever changes and an old URL is visited.

### SEO metadata falls back gracefully, sitemap/robots are data-driven

`generateMetadata` on product/category pages calls the backend's `GET /seo/{type}/{id}` endpoint (Phase 04) and falls back to the entity's own name/description when no SEO row exists yet - most entities won't have one configured this early. `app/sitemap.ts` and `app/robots.ts` build their URL lists from live catalog data (bounded to 100 categories/products each) rather than a static route list, and `sitemap.ts` is explicitly marked `force-dynamic` for the same build-safety reason as the rest of the app.

### No cart/checkout, by instruction

Product pages show variant pricing and a variant selector but no "Add to cart" action, and the Contact page shows settings-sourced contact details rather than a form that would submit nowhere. Both are deliberate: building UI that implies a working action it doesn't have would be misleading, and cart/checkout is explicitly out of scope for this phase.

## Consequences

- 5 route segments beyond the existing foundation (`/`, `/categories`, `/categories/[id]/[slug]`, `/products/[id]/[slug]`, `/search`, `/about`, `/contact`, `/faq`) plus `sitemap.ts`/`robots.ts`, ~25 components, and a typed API-client layer (`src/lib/api/*.ts`, `src/types/api.ts`) mirroring the Phase 04 backend resources field-for-field.
- 21 frontend tests (query/pagination helpers, pricing/discount logic, route-building, component rendering) plus `typecheck`/`lint`/`build`, all passing; the storefront was also manually verified end-to-end in a browser against a locally seeded backend (home, product detail, category browsing with filter/sort, search, a 404 for a nonexistent product, and the mobile viewport).
- Deliberately out of scope, same reasoning as prior phases: cart/checkout, account/order-tracking pages, the admin panel, and the AI chatbot widget. All are natural next layers on top of what exists now, not missing pieces of this phase.
